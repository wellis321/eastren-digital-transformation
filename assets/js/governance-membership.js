/**
 * Governance membership tool — per-user opinions + shared membership lists.
 * Names and roles only (no emails).
 */
(function () {
  'use strict';

  const API_URL = 'governance-membership-api.php';
  const DEFAULT_GROUP_ORDER = ['project-board', 'steering-group', 'technical-group', 'business-group'];

  const groupsEl = document.getElementById('gov-groups');
  const poolEl = document.getElementById('gov-pool-body');
  const statusEl = document.getElementById('gov-status');
  const awaitingEl = document.getElementById('gov-awaiting-body');
  const jumpEl = document.querySelector('.gov-jump-links') || document.querySelector('.gov-jump');

  if (!groupsEl) return;

  const esc = (s) => {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  };

  const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  /** PHP json_encode turns empty assoc arrays into [] — never treat those as maps. */
  const asPlainObject = (value) => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
    return value;
  };

  let people = [];
  let groupMeta = {};
  let groupOrder = DEFAULT_GROUP_ORDER.slice();
  let state = { order: {}, membership: {} };
  let myVotes = {};
  let myVoteNotes = {};
  let tallies = {};
  let needsAssessment = [];
  let canEdit = true;
  let canVote = true;
  let canShape = true;
  let canPropose = true;
  let canConfirmMembership = false;
  let canSetGroupStatus = false;
  let proposals = [];
  let groupStatusLabels = {
    development: 'In development',
    active: 'Active / stable',
    inactive: 'Inactive',
    dissolved: 'Dissolved',
  };
  let currentUser = { id: 0, label: '' };
  let dbInitialized = false;
  let saveTimer = null;
  let dirty = false;
  let historyEntries = [];
  let selectedHistoryId = null;
  let historyFilter = 'all';
  let historyTotal = 0;
  let historyHasMore = false;
  let historyLoading = false;
  const HISTORY_PAGE_SIZE = 25;
  let selectedHistoryEntry = null;
  let snapshotStatusFilter = 'all';
  let snapshotSearchQuery = '';

  const HISTORY_REASONS = {
    membership_save: 'Membership updated',
    membership_remove: 'Person removed',
    place_add: 'Also placed on a group',
    place_move: 'Moved between groups',
    add_person: 'Person added',
    create_group: 'Group created',
    delete_group: 'Group deleted',
    checkpoint: 'Checkpoint',
    baseline: 'Starting baseline',
    group_status: 'Group status changed',
    restore: 'Restored from history',
    reseed_unassigned: 'Reseeded unassigned from seed list',
  };

  const setStatus = (msg, isError) => {
    if (!statusEl) return;
    statusEl.textContent = msg;
    statusEl.classList.toggle('gov-toolbar-note--error', !!isError);
  };

  const personById = (id) => people.find((p) => p.id === id);

  const groupsFor = (person) => {
    if (dbInitialized && state.membership && Object.keys(state.membership).length) {
      return Array.isArray(state.membership[person.id]) ? state.membership[person.id].slice() : [];
    }
    const override = state.membership[person.id];
    if (Array.isArray(override)) return override.slice();
    return Array.isArray(person.groups) ? person.groups.slice() : [];
  };

  const effectiveMembership = () => {
    const map = {};
    people.forEach((p) => {
      const g = groupsFor(p);
      if (g.length) map[p.id] = g;
    });
    return map;
  };

  const getMyVote = (personId, groupId) => {
    const v = myVotes?.[personId]?.[groupId];
    return v === 'yes' || v === 'maybe' || v === 'no' ? v : 'unset';
  };

  const getMyVoteNote = (personId, groupId) => {
    const n = myVoteNotes?.[personId]?.[groupId];
    return typeof n === 'string' ? n : '';
  };

  const getTally = (personId, groupId) => {
    const t = tallies?.[personId]?.[groupId];
    if (!t || typeof t !== 'object') {
      return { yes: 0, maybe: 0, no: 0, total: 0 };
    }
    return {
      yes: Number(t.yes) || 0,
      maybe: Number(t.maybe) || 0,
      no: Number(t.no) || 0,
      total: Number(t.total) || 0,
    };
  };

  /** Sort key: more Yes floats up; more No sinks. Manual order breaks ties. */
  const tallyRank = (personId, groupId) => {
    const t = getTally(personId, groupId);
    return -(t.yes * 100 + t.maybe * 10 - t.no);
  };

  const membersForGroup = (groupId) => people.filter((p) => groupsFor(p).includes(groupId));

  const unassignedPeople = () => people.filter((p) => groupsFor(p).length === 0);

  const needsKey = (personId, groupId) =>
    needsAssessment.some((n) => n.personId === personId && n.groupId === groupId);

  const sortedIdsForGroup = (groupId) => {
    const members = membersForGroup(groupId);
    const existingOrder = Array.isArray(state.order[groupId]) ? state.order[groupId] : [];
    const orderIndex = new Map(existingOrder.map((id, i) => [id, i]));

    members.sort((a, b) => {
      const ra = tallyRank(a.id, groupId);
      const rb = tallyRank(b.id, groupId);
      if (ra !== rb) return ra - rb;
      const ia = orderIndex.has(a.id) ? orderIndex.get(a.id) : 9999;
      const ib = orderIndex.has(b.id) ? orderIndex.get(b.id) : 9999;
      if (ia !== ib) return ia - ib;
      return a.name.localeCompare(b.name, 'en-GB');
    });

    return members.map((p) => p.id);
  };

  const ensureOrder = (groupId) => {
    if (!Array.isArray(state.order[groupId]) || !state.order[groupId].length) {
      state.order[groupId] = sortedIdsForGroup(groupId);
      return;
    }
    state.order[groupId] = sortedIdsForGroup(groupId);
  };

  const applyBundle = (bundle) => {
    people = Array.isArray(bundle.people) ? bundle.people : people;
    groupMeta = bundle.groups || groupMeta;
    groupOrder =
      Array.isArray(bundle.groupOrder) && bundle.groupOrder.length
        ? bundle.groupOrder.slice()
        : Object.keys(groupMeta).length
          ? Object.keys(groupMeta)
          : DEFAULT_GROUP_ORDER.slice();
    state = {
      order: asPlainObject(bundle.order),
      membership: asPlainObject(bundle.membership),
    };
    myVotes = asPlainObject(bundle.myVotes);
    myVoteNotes = asPlainObject(bundle.myVoteNotes);
    tallies = asPlainObject(bundle.tallies);
    needsAssessment = Array.isArray(bundle.needsAssessment) ? bundle.needsAssessment : [];
    canEdit = bundle.canEdit !== false;
    canVote = bundle.canVote !== false;
    canShape = bundle.canShape !== false;
    canPropose = bundle.canPropose !== false;
    canConfirmMembership = !!bundle.canConfirmMembership || !!bundle.canSetGroupStatus;
    canSetGroupStatus = !!bundle.canSetGroupStatus;
    proposals = Array.isArray(bundle.proposals) ? bundle.proposals : [];
    if (bundle.groupStatuses && typeof bundle.groupStatuses === 'object' && !Array.isArray(bundle.groupStatuses)) {
      groupStatusLabels = bundle.groupStatuses;
    }
    currentUser = bundle.currentUser || currentUser;
    dbInitialized = !!bundle.dbInitialized || Object.keys(state.membership).length > 0;
  };

  const postJson = (payload) => {
    const token = csrfToken();
    if (!token) {
      return Promise.reject(
        new Error('Cannot save — refresh while signed in through the PHP portal (php -S … router.php).')
      );
    }
    return fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token,
      },
      body: JSON.stringify({ ...payload, csrf_token: token }),
      credentials: 'same-origin',
    }).then(async (r) => {
      const data = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(data.error || 'Save failed');
      return data;
    });
  };

  const persistSoon = () => {
    if (!canShape) return;
    dirty = true;
    setStatus('Saving membership…');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(persistNow, 350);
  };

  const persistNow = () => {
    if (!canShape) return Promise.resolve();
    groupOrder.forEach(ensureOrder);
    return postJson({
      action: 'save',
      order: asPlainObject(state.order),
      membership: effectiveMembership(),
    })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus('Membership saved');
        render();
        refreshHistoryQuiet();
      })
      .catch((err) => {
        setStatus(err.message || 'Save failed', true);
      });
  };

  const setVote = (personId, groupId, vote) => {
    if (!canVote) {
      setStatus('Sign in to record your opinion', true);
      return;
    }
    myVotes = asPlainObject(myVotes);
    if (!myVotes[personId]) myVotes[personId] = {};

    const prev = getMyVote(personId, groupId);
    myVoteNotes = asPlainObject(myVoteNotes);
    if (vote === 'unset') {
      delete myVotes[personId][groupId];
      if (!Object.keys(myVotes[personId]).length) delete myVotes[personId];
      if (myVoteNotes[personId]) {
        delete myVoteNotes[personId][groupId];
        if (!Object.keys(myVoteNotes[personId]).length) delete myVoteNotes[personId];
      }
    } else {
      myVotes[personId][groupId] = vote;
    }

    // Optimistic tally adjust for current user only
    if (!tallies[personId]) tallies[personId] = {};
    if (!tallies[personId][groupId]) {
      tallies[personId][groupId] = { yes: 0, maybe: 0, no: 0, total: 0 };
    }
    const t = tallies[personId][groupId];
    if (prev !== 'unset') {
      t[prev] = Math.max(0, (Number(t[prev]) || 0) - 1);
      t.total = Math.max(0, (Number(t.total) || 0) - 1);
    }
    if (vote !== 'unset') {
      t[vote] = (Number(t[vote]) || 0) + 1;
      t.total = (Number(t.total) || 0) + 1;
    }

    needsAssessment = needsAssessment.filter(
      (n) => !(n.personId === personId && n.groupId === groupId)
    );
    dirty = true;
    state.order[groupId] = sortedIdsForGroup(groupId);
    render();
    setStatus('Saving your opinion…');

    postJson({
      action: 'set_vote',
      personId,
      groupId,
      vote,
    })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus('Your opinion saved — others see the updated tally');
        render();
      })
      .catch((err) => {
        setStatus(err.message || 'Could not save opinion', true);
      });
  };

  let voteNoteTimer = null;
  const setVoteNote = (personId, groupId, comment, { quiet = false } = {}) => {
    if (!canVote) return;
    if (getMyVote(personId, groupId) === 'unset') {
      if (!quiet) setStatus('Choose Yes / Maybe / No before adding a private note', true);
      return;
    }
    myVoteNotes = asPlainObject(myVoteNotes);
    if (!myVoteNotes[personId]) myVoteNotes[personId] = {};
    const trimmed = String(comment || '').trim().slice(0, 500);
    if (trimmed) myVoteNotes[personId][groupId] = trimmed;
    else {
      delete myVoteNotes[personId][groupId];
      if (!Object.keys(myVoteNotes[personId]).length) delete myVoteNotes[personId];
    }
    if (!quiet) setStatus('Saving your private note…');
    postJson({
      action: 'set_vote_note',
      personId,
      groupId,
      comment: trimmed,
    })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        if (!quiet) setStatus('Private note saved — only you can see it');
      })
      .catch((err) => setStatus(err.message || 'Could not save note', true));
  };

  const queueVoteNote = (personId, groupId, comment) => {
    clearTimeout(voteNoteTimer);
    voteNoteTimer = setTimeout(() => setVoteNote(personId, groupId, comment, { quiet: true }), 450);
  };

  const placePerson = (personId, toGroupId, mode, fromGroupId) => {
    if (!toGroupId) {
      setStatus('Choose a target group first', true);
      return;
    }
    if (canShape) {
      setStatus(mode === 'move' ? 'Moving…' : 'Adding to group…');
      postJson({
        action: 'place_person',
        personId,
        toGroupId,
        mode,
        fromGroupId: mode === 'move' ? fromGroupId : null,
      })
        .then((data) => {
          if (data.state) applyBundle(data.state);
          dirty = false;
          const label = groupMeta[toGroupId]?.label || toGroupId;
          setStatus(
            mode === 'move'
              ? `Moved to ${label} — others will be asked to assess them there`
              : `Also added to ${label} — others will be asked to assess them there`
          );
          render();
          const panel = document.getElementById(toGroupId);
          if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
          refreshHistoryQuiet();
        })
        .catch((err) => setStatus(err.message || 'Could not update group membership', true));
      return;
    }
    if (!canPropose) {
      setStatus('Sign in to suggest a membership change', true);
      return;
    }
    setStatus('Sending suggestion…');
    postJson({
      action: 'propose',
      personId,
      groupId: toGroupId,
      proposalAction: mode === 'move' ? 'move' : 'add',
      fromGroupId: mode === 'move' ? fromGroupId : null,
    })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus(
          mode === 'move'
            ? 'Move suggested — shown as tentative until a confirmer accepts'
            : 'Add suggested — shown as tentative until a confirmer accepts'
        );
        render();
      })
      .catch((err) => setStatus(err.message || 'Could not send suggestion', true));
  };

  const addToGroup = (personId, groupId) => {
    placePerson(personId, groupId, 'add', null);
  };

  const removeFromGroup = (personId, groupId) => {
    if (canShape) {
      setStatus('Removing…');
      postJson({ action: 'remove_person', personId, groupId })
        .then((data) => {
          if (data.state) applyBundle(data.state);
          dirty = false;
          setStatus('Removed from group');
          render();
          refreshHistoryQuiet();
        })
        .catch((err) => setStatus(err.message || 'Could not remove', true));
      return;
    }
    if (!canPropose) {
      setStatus('Sign in to suggest a removal', true);
      return;
    }
    setStatus('Sending removal suggestion…');
    postJson({
      action: 'propose',
      personId,
      groupId,
      proposalAction: 'remove',
    })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus('Removal suggested — person stays on the list until a confirmer accepts');
        render();
      })
      .catch((err) => setStatus(err.message || 'Could not send suggestion', true));
  };

  const addNewPerson = () => {
    if (!canShape) return;
    const nameInput = document.getElementById('gov-new-name');
    const roleInput = document.getElementById('gov-new-role');
    const groupSelect = document.getElementById('gov-new-group');
    const name = (nameInput?.value || '').trim();
    const role = (roleInput?.value || '').trim();
    const groupId = groupSelect?.value || '';
    if (!name || !role || !groupId) {
      setStatus('Enter a name, job role, and group before adding', true);
      return;
    }
    setStatus('Adding person…');
    postJson({
      action: 'add_person',
      name,
      role,
      groupId,
    })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        if (nameInput) nameInput.value = '';
        if (roleInput) roleInput.value = '';
        setStatus('Added — each signed-in user is asked for their own Yes / Maybe / No');
        render();
        const panel = document.getElementById(groupId);
        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        refreshHistoryQuiet();
      })
      .catch((err) => setStatus(err.message || 'Could not add person', true));
  };

  const createGroup = () => {
    if (!canShape) return;
    const labelInput = document.getElementById('gov-new-group-label');
    const blurbInput = document.getElementById('gov-new-group-blurb');
    const label = (labelInput?.value || '').trim();
    const blurb = (blurbInput?.value || '').trim();
    if (!label) {
      setStatus('Enter a name for the new group', true);
      return;
    }
    setStatus('Creating group…');
    postJson({
      action: 'create_group',
      label,
      blurb,
    })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        if (labelInput) labelInput.value = '';
        if (blurbInput) blurbInput.value = '';
        const id = data.created?.groupId;
        setStatus(`Created “${data.created?.label || label}” — add people with Also on / Move to`);
        render();
        if (id) {
          const panel = document.getElementById(id);
          if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        refreshHistoryQuiet();
      })
      .catch((err) => setStatus(err.message || 'Could not create group', true));
  };

  const statusLabel = (status) =>
    groupStatusLabels[status] || groupStatusLabels.development || 'In development';

  const setGroupStatus = (groupId, status) => {
    if (!canSetGroupStatus) {
      setStatus('Only an administrator can change group status', true);
      return;
    }
    if (!groupId || !status) return;
    const prev = groupMeta[groupId]?.status || 'development';
    if (prev === status) return;
    setStatus('Updating group status…');
    postJson({ action: 'set_group_status', groupId, status })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus(`Status set to ${statusLabel(status)}`);
        render();
        refreshHistoryQuiet();
      })
      .catch((err) => setStatus(err.message || 'Could not update status', true));
  };

  const deleteGroup = (groupId) => {
    if (!canShape) return;
    const meta = groupMeta[groupId];
    if (!meta || meta.isCore) {
      setStatus('Core groups cannot be deleted', true);
      return;
    }
    if (
      !confirm(
        `Delete “${meta.label}” and remove everyone from it? Opinions for that list are cleared. This cannot be undone.`
      )
    ) {
      return;
    }
    setStatus('Deleting group…');
    postJson({ action: 'delete_group', groupId })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus(`Deleted “${meta.label}”`);
        render();
        refreshHistoryQuiet();
      })
      .catch((err) => setStatus(err.message || 'Could not delete group', true));
  };

  const voteIcon = (vote) => {
    if (vote === 'yes') return '<span class="gov-vote-icon gov-vote-icon--yes" title="Your mark: Yes">Y</span>';
    if (vote === 'maybe') return '<span class="gov-vote-icon gov-vote-icon--maybe" title="Your mark: Maybe">~</span>';
    if (vote === 'no') return '<span class="gov-vote-icon gov-vote-icon--no" title="Your mark: No">N</span>';
    return '<span class="gov-vote-icon gov-vote-icon--unset" title="You have not marked yet">·</span>';
  };

  const otherGroupOptions = (personId, currentGroupId) => {
    const on = new Set(groupsFor(personById(personId) || { id: personId, groups: [currentGroupId] }));
    return groupOrder
      .filter((g) => g !== currentGroupId && !on.has(g))
      .map((g) => `<option value="${esc(g)}">${esc(groupMeta[g]?.label || g)}</option>`)
      .join('');
  };

  const proposalsForGroup = (groupId) => proposals.filter((p) => p.groupId === groupId);

  const proposalForPersonGroup = (personId, groupId) => {
    const direct = proposals.find((p) => p.personId === personId && p.groupId === groupId);
    if (direct) return direct;
    return (
      proposals.find(
        (p) => p.action === 'move' && p.personId === personId && p.fromGroupId === groupId
      ) || null
    );
  };

  const decideProposal = (proposalId, decision) => {
    if (!canShape) {
      setStatus('Only a membership confirmer can accept or reject suggestions', true);
      return;
    }
    setStatus(decision === 'accept' ? 'Accepting…' : 'Rejecting…');
    postJson({ action: 'decide_proposal', proposalId, decision })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus(decision === 'accept' ? 'Suggestion accepted' : 'Suggestion rejected');
        render();
        refreshHistoryQuiet();
      })
      .catch((err) => setStatus(err.message || 'Could not update suggestion', true));
  };

  const withdrawProposal = (proposalId) => {
    setStatus('Withdrawing suggestion…');
    postJson({ action: 'withdraw_proposal', proposalId })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus('Suggestion withdrawn');
        render();
      })
      .catch((err) => setStatus(err.message || 'Could not withdraw', true));
  };

  const renderProposalActions = (prop) => {
    if (!prop) return '';
    const mine = currentUser.id && prop.proposedByUserId === currentUser.id;
    if (canShape) {
      return `<div class="gov-proposal-actions">
        <button type="button" class="gov-btn" data-action="accept-proposal" data-proposal-id="${prop.id}">Accept</button>
        <button type="button" class="gov-btn gov-btn--muted" data-action="reject-proposal" data-proposal-id="${prop.id}">Reject</button>
      </div>`;
    }
    if (mine || canShape) {
      return `<div class="gov-proposal-actions">
        <button type="button" class="gov-btn gov-btn--muted" data-action="withdraw-proposal" data-proposal-id="${prop.id}">Withdraw</button>
      </div>`;
    }
    return '';
  };

  const proposalActionLabel = (prop, viewingGroupId) => {
    if (prop.action === 'remove') return 'Suggested removal';
    if (prop.action === 'move') {
      if (viewingGroupId && prop.fromGroupId === viewingGroupId) {
        return `Suggested move to ${prop.groupLabel || prop.groupId}`;
      }
      return `Suggested move from ${prop.fromGroupLabel || prop.fromGroupId || 'another group'}`;
    }
    return 'Suggested addition';
  };

  const renderPlaceControls = (person, groupId) => {
    if (!canShape && !canPropose) return '';
    const opts = otherGroupOptions(person.id, groupId);
    if (!opts) {
      return `<details class="gov-place-details"><summary>Place on another group</summary><p class="gov-place-note">Already on every group</p></details>`;
    }
    const alsoLabel = canShape ? 'Also on' : 'Suggest also on';
    const moveLabel = canShape ? 'Move to' : 'Suggest move';
    const summary = canShape ? 'Place on another group' : 'Suggest place on another group';
    return `<details class="gov-place-details">
      <summary>${summary}</summary>
      <div class="gov-place">
        <label class="visually-hidden" for="place-${esc(person.id)}-${esc(groupId)}">Place ${esc(person.name)} on another group</label>
        <select id="place-${esc(person.id)}-${esc(groupId)}" data-place-select="${esc(person.id)}" data-from-group="${esc(groupId)}">
          <option value="">Choose group…</option>
          ${opts}
        </select>
        <button type="button" class="gov-btn gov-btn--muted" data-action="also-on" data-id="${esc(person.id)}" data-group="${esc(groupId)}" title="${canShape ? 'Keep on this list and also add to the chosen group' : 'Suggest adding them to another group (needs confirmation)'}">${alsoLabel}</button>
        <button type="button" class="gov-btn gov-btn--muted" data-action="move-to" data-id="${esc(person.id)}" data-group="${esc(groupId)}" title="${canShape ? 'Remove from this list and put on the chosen group' : 'Suggest moving them (needs confirmation)'}">${moveLabel}</button>
      </div>
    </details>`;
  };

  const renderTallyCompact = (personId, groupId) => {
    const t = getTally(personId, groupId);
    if (!t.total) {
      return `<span class="gov-tally-compact gov-tally-compact--empty" title="No opinions yet">—</span>`;
    }
    return `<span class="gov-tally-compact" title="${t.yes} Yes · ${t.maybe} Maybe · ${t.no} No">
      <span class="gov-tally-compact__yes">${t.yes}Y</span>
      <span class="gov-tally-compact__maybe">${t.maybe}~</span>
      <span class="gov-tally-compact__no">${t.no}N</span>
    </span>`;
  };

  const renderRow = (person, groupId) => {
    const vote = getMyVote(person.id, groupId);
    const voteDisabled = canVote ? '' : ' disabled';
    const awaiting = needsKey(person.id, groupId)
      ? '<span class="gov-mark-needed" title="You have not yet given Yes, Maybe, or No for this person on this group">Your mark needed</span>'
      : '';
    const prop = proposalForPersonGroup(person.id, groupId);
    const tentativeRemove =
      prop && (prop.action === 'remove' || (prop.action === 'move' && prop.fromGroupId === groupId));
    const tentativeBadge = prop
      ? `<span class="gov-tentative-badge" title="Suggested by ${esc(prop.proposedBy)}">${esc(proposalActionLabel(prop, groupId))}</span>`
      : '';
    const otherLists = groupsFor(person)
      .filter((g) => g !== groupId)
      .map((g) => groupMeta[g]?.label || g);
    const alsoOn =
      otherLists.length > 0
        ? `<span class="gov-also-on">Also: ${esc(otherLists.join(', '))}</span>`
        : '';
    let removeBtn = '';
    if (canShape) {
      removeBtn = `<button type="button" class="gov-icon-btn gov-icon-btn--danger" data-action="remove" data-id="${esc(person.id)}" data-group="${esc(groupId)}" title="Remove from this group" aria-label="Remove from group">×</button>`;
    } else if (canPropose && !tentativeRemove) {
      removeBtn = `<button type="button" class="gov-icon-btn gov-icon-btn--danger" data-action="remove" data-id="${esc(person.id)}" data-group="${esc(groupId)}" title="Suggest removal (needs confirmation)" aria-label="Suggest removal">×</button>`;
    }
    const rowId = `member-${esc(groupId)}-${esc(person.id)}`;
    const rowClass = tentativeRemove ? ' gov-person-row--tentative-remove' : '';
    return `<li class="gov-person-row${rowClass}" id="${rowId}" data-id="${esc(person.id)}" data-vote="${esc(vote)}">
      <div class="gov-person-main">
        <div class="gov-person-icons" aria-hidden="true">${voteIcon(vote)}</div>
        <div class="gov-person-text">
          <div class="gov-person-line">
            <strong class="gov-person-name">${esc(person.name)}</strong>
            ${awaiting}
            ${tentativeBadge}
            ${renderTallyCompact(person.id, groupId)}
          </div>
          <span class="gov-person-role">${esc(person.role || 'Role not recorded')}</span>
          ${alsoOn}
          ${prop ? `<p class="gov-proposal-meta">Suggested by ${esc(prop.proposedBy)}</p>${renderProposalActions(prop)}` : ''}
          ${renderPlaceControls(person, groupId)}
        </div>
      </div>
      <div class="gov-person-actions">
        <div class="gov-vote-btns" role="group" aria-label="Your mark for ${esc(person.name)}">
          <button type="button" class="gov-vote-btn${vote === 'yes' ? ' is-active' : ''}" data-action="vote" data-vote="yes" data-id="${esc(person.id)}" data-group="${esc(groupId)}"${voteDisabled}>Yes</button>
          <button type="button" class="gov-vote-btn${vote === 'maybe' ? ' is-active' : ''}" data-action="vote" data-vote="maybe" data-id="${esc(person.id)}" data-group="${esc(groupId)}"${voteDisabled}>Maybe</button>
          <button type="button" class="gov-vote-btn${vote === 'no' ? ' is-active' : ''}" data-action="vote" data-vote="no" data-id="${esc(person.id)}" data-group="${esc(groupId)}"${voteDisabled}>No</button>
          <button type="button" class="gov-vote-btn gov-vote-btn--clear" data-action="vote" data-vote="unset" data-id="${esc(person.id)}" data-group="${esc(groupId)}" title="Clear your mark"${voteDisabled}>Clear</button>
        </div>
        ${
          canVote && vote !== 'unset'
            ? `<label class="gov-vote-note">
          <span class="visually-hidden">Private reason for your mark on ${esc(person.name)}</span>
          <input type="text" class="gov-vote-note-input" maxlength="500" placeholder="Private reason (optional)…" value="${esc(getMyVoteNote(person.id, groupId))}" data-action="vote-note" data-id="${esc(person.id)}" data-group="${esc(groupId)}"${voteDisabled}>
        </label>`
            : ''
        }
        ${removeBtn}
      </div>
    </li>`;
  };

  const renderTentativeAddRow = (prop) => {
    return `<li class="gov-person-row gov-person-row--tentative-add" id="proposal-${prop.id}">
      <div class="gov-person-main">
        <div class="gov-person-icons" aria-hidden="true"><span class="gov-vote-icon gov-vote-icon--unset" title="Tentative">?</span></div>
        <div class="gov-person-text">
          <div class="gov-person-line">
            <strong class="gov-person-name">${esc(prop.personName)}</strong>
            <span class="gov-tentative-badge">${esc(proposalActionLabel(prop))}</span>
          </div>
          <span class="gov-person-role">${esc(prop.personRole || 'Role not recorded')}</span>
          <p class="gov-proposal-meta">Suggested by ${esc(prop.proposedBy)} — not on the live list until accepted</p>
          ${renderProposalActions(prop)}
        </div>
      </div>
    </li>`;
  };

  const renderRoster = (groupId, peopleList) => {
    const pendingAdds = proposalsForGroup(groupId).filter((p) => p.action === 'add' || p.action === 'move');
    if (!peopleList.length && !pendingAdds.length) return '';
    const memberChips = peopleList
      .map((p) => {
        const vote = getMyVote(p.id, groupId);
        const pending = needsKey(p.id, groupId) ? ' gov-roster-chip--pending' : '';
        const prop = proposalForPersonGroup(p.id, groupId);
        const tentative =
          prop &&
          (prop.action === 'remove' || (prop.action === 'move' && prop.fromGroupId === groupId))
            ? ' gov-roster-chip--tentative-remove'
            : '';
        const voteClass =
          vote === 'yes'
            ? ' gov-roster-chip--yes'
            : vote === 'maybe'
              ? ' gov-roster-chip--maybe'
              : vote === 'no'
                ? ' gov-roster-chip--no'
                : '';
        return `<li><a class="gov-roster-chip${voteClass}${pending}${tentative}" href="#member-${esc(groupId)}-${esc(p.id)}">${esc(p.name)}</a></li>`;
      })
      .join('');
    const addChips = pendingAdds
      .map(
        (p) =>
          `<li><a class="gov-roster-chip gov-roster-chip--tentative-add" href="#proposal-${p.id}">${esc(p.personName)} (suggested)</a></li>`
      )
      .join('');
    return `<ul class="gov-roster" aria-label="Members at a glance">${memberChips}${addChips}</ul>`;
  };

  const renderGroup = (groupId) => {
    const meta = groupMeta[groupId] || {
      label: groupId,
      blurb: '',
      isCore: true,
      status: 'development',
    };
    ensureOrder(groupId);
    const ids = state.order[groupId] || sortedIdsForGroup(groupId);
    state.order[groupId] = ids;
    const members = ids.map((id) => personById(id)).filter(Boolean);
    const groupProps = proposalsForGroup(groupId);
    const pendingAdds = groupProps.filter((p) => p.action === 'add' || p.action === 'move');
    const rows = members.map((p) => renderRow(p, groupId)).join('');
    const tentativeRows = pendingAdds.map(renderTentativeAddRow).join('');
    const count = members.length;
    const suggestNote = pendingAdds.length
      ? ` · ${pendingAdds.length} suggested`
      : '';
    const countLabel = count === 1 ? '1 person' : `${count} people`;
    const empty =
      !ids.length && !pendingAdds.length
        ? `<p class="gov-empty">No members yet — add someone, move them here, or use the unassigned pool.</p>`
        : '';
    const tools = [];
    if (canVote) {
      tools.push(
        `<button type="button" class="gov-btn gov-btn--muted" data-action="reset-group" data-group="${esc(groupId)}">Clear my marks</button>`
      );
    }
    if (canShape && meta.isCore === false) {
      tools.push(
        `<button type="button" class="gov-btn gov-btn--muted" data-action="delete-group" data-group="${esc(groupId)}">Delete group</button>`
      );
    }
    const status = meta.status || 'development';
    const statusClass = `gov-status-badge gov-status-badge--${esc(status)}`;
    const statusBadge = `<span class="${statusClass}">${esc(statusLabel(status))}</span>`;
    const customClass = meta.isCore === false ? ' gov-group-panel--custom' : '';
    const dissolvedClass = status === 'dissolved' || status === 'inactive' ? ` gov-group-panel--${status}` : '';
    const customBadge =
      meta.isCore === false ? '<span class="gov-group-badge">Custom</span>' : '';
    const statusRow = canSetGroupStatus
      ? `<div class="gov-status-row">
          <label class="gov-status-control">
            <span class="gov-status-label">Group status</span>
            <select data-action="set-status" data-group="${esc(groupId)}" title="Lifecycle status for this group">
              ${Object.entries(groupStatusLabels)
                .map(
                  ([id, label]) =>
                    `<option value="${esc(id)}"${id === status ? ' selected' : ''}>${esc(label)}</option>`
                )
                .join('')}
            </select>
          </label>
        </div>`
      : '';
    const toolsHtml = tools.length ? `<div class="gov-group-tools">${tools.join('')}</div>` : '';
    const listHtml =
      members.length || pendingAdds.length
        ? `<h3 class="gov-assess-heading">Assess &amp; place</h3><ul class="gov-person-list">${rows}${tentativeRows}</ul>`
        : '';

    return `<section class="gov-group-panel${customClass}${dissolvedClass}" id="${esc(groupId)}">
      <header class="gov-group-head">
        <p class="gov-group-kicker">${esc(countLabel)}${esc(suggestNote)}${customBadge}${statusBadge}</p>
        <h2 class="gov-group-title">${esc(meta.label || groupId)}</h2>
        ${meta.blurb ? `<p class="gov-group-blurb">${esc(meta.blurb)}</p>` : ''}
        <div class="gov-group-actions">
          ${statusRow}
          ${toolsHtml}
        </div>
      </header>
      ${renderRoster(groupId, members)}
      ${empty}
      ${listHtml}
    </section>`;
  };

  const renderAwaiting = () => {
    if (!awaitingEl) return;
    if (!needsAssessment.length) {
      awaitingEl.innerHTML = `<span class="gov-awaiting-status">You have no new people left to assess.</span>`;
      return;
    }
    awaitingEl.innerHTML = `<ul class="gov-awaiting-list">${needsAssessment
      .map(
        (n) => `<li>
          <strong>${esc(n.name)}</strong>
          <span class="gov-person-role">${esc(n.role || '')}</span>
          <span class="gov-group-badge">${esc(n.groupLabel || n.groupId)}</span>
          <a href="#${esc(n.groupId)}">Assess</a>
        </li>`
      )
      .join('')}</ul>`;
  };

  const renderJump = () => {
    if (!jumpEl) return;
    const pending = needsAssessment.length;
    const awaitingLabel =
      pending > 0 ? `Awaiting <span class="gov-jump-count">${pending}</span>` : 'Awaiting';
    const poolCount = unassignedPeople().length;
    const links = [
      `<a href="#awaiting">${awaitingLabel}</a>`,
      ...groupOrder.map((g) => {
        const n = membersForGroup(g).length;
        const st = groupMeta[g]?.status || 'development';
        const stShort =
          st === 'active'
            ? 'Active'
            : st === 'inactive'
              ? 'Inactive'
              : st === 'dissolved'
                ? 'Dissolved'
                : 'Dev';
        return `<a href="#${esc(g)}">${esc(groupMeta[g]?.label || g)} <span class="gov-jump-count">${n}</span> <span class="gov-jump-status gov-jump-status--${esc(st)}">${stShort}</span></a>`;
      }),
      `<a href="#pool">Unassigned <span class="gov-jump-count">${poolCount}</span></a>`,
      `<a href="#group-history">History</a>`,
    ];
    jumpEl.innerHTML = links.join('');
  };

  const syncGroupSelects = () => {
    const opts = groupOrder
      .map((g) => `<option value="${esc(g)}">${esc(groupMeta[g]?.label || g)}</option>`)
      .join('');
    const addSelect = document.getElementById('gov-new-group');
    if (addSelect) {
      const prev = addSelect.value;
      addSelect.innerHTML = opts;
      if (prev && groupOrder.includes(prev)) addSelect.value = prev;
    }
  };

  const renderPool = () => {
    if (!poolEl) return;
    const list = unassignedPeople();
    if (!list.length) {
      poolEl.innerHTML = `<p class="gov-empty">Everyone is assigned to at least one group.</p>`;
      return;
    }
    const groupOpts = groupOrder
      .map((g) => `<option value="${esc(g)}">${esc(groupMeta[g]?.label || g)}</option>`)
      .join('');
    poolEl.innerHTML = `<ul class="gov-pool-list">${list
      .map((p) => {
    const add = canShape || canPropose
      ? `<div class="gov-pool-add">
            <label class="visually-hidden" for="add-${esc(p.id)}">Add ${esc(p.name)} to group</label>
            <select id="add-${esc(p.id)}" data-add-select="${esc(p.id)}">${groupOpts}</select>
            <button type="button" class="gov-btn" data-action="add" data-id="${esc(p.id)}">${canShape ? 'Add' : 'Suggest add'}</button>
          </div>`
      : '';
        return `<li class="gov-pool-row">
          <div>
            <strong>${esc(p.name)}</strong>
            <span class="gov-person-role">${esc(p.role || 'Role not recorded')}</span>
          </div>
          ${add}
        </li>`;
      })
      .join('')}</ul>`;
  };

  const render = () => {
    groupsEl.innerHTML = groupOrder.map(renderGroup).join('');
    renderAwaiting();
    renderPool();
    renderJump();
    syncGroupSelects();
    const resetBtn = document.getElementById('gov-reset-all');
    const addBtn = document.getElementById('gov-add-person');
    const createBtn = document.getElementById('gov-create-group');
    if (resetBtn) resetBtn.disabled = !canVote;
    if (addBtn) addBtn.disabled = !canShape;
    if (createBtn) createBtn.disabled = !canShape;
    if (!dirty) {
      const pending = needsAssessment.length;
      const pendingNote = pending ? ` · ${pending} awaiting your mark` : '';
      const who = currentUser.label ? ` as ${currentUser.label}` : '';
      setStatus(
        canVote
          ? `Ready — one opinion per person${who}${pendingNote}`
          : `Signed in required to mark opinions${pendingNote}`
      );
    }
  };

  const resetGroupMarks = (groupId) => {
    if (!canVote) return;
    if (!confirm('Clear only your Yes / Maybe / No marks for this group? Other people’s opinions stay.')) {
      return;
    }
    setStatus('Clearing your marks…');
    postJson({ action: 'clear_my_votes', groupId })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus('Your marks for this group were cleared');
        render();
      })
      .catch((err) => setStatus(err.message || 'Could not clear marks', true));
  };

  groupsEl.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn || btn.disabled) return;
    e.preventDefault();
    const action = btn.getAttribute('data-action');
    const id = btn.getAttribute('data-id');
    const group = btn.getAttribute('data-group');
    if (action === 'vote') setVote(id, group, btn.getAttribute('data-vote'));
    if (action === 'remove') removeFromGroup(id, group);
    if (action === 'reset-group') resetGroupMarks(group);
    if (action === 'delete-group') deleteGroup(group);
    if (action === 'accept-proposal') decideProposal(Number(btn.getAttribute('data-proposal-id')), 'accept');
    if (action === 'reject-proposal') decideProposal(Number(btn.getAttribute('data-proposal-id')), 'reject');
    if (action === 'withdraw-proposal') withdrawProposal(Number(btn.getAttribute('data-proposal-id')));
    if (action === 'also-on' || action === 'move-to') {
      const select = groupsEl.querySelector(
        `select[data-place-select="${CSS.escape(id)}"][data-from-group="${CSS.escape(group)}"]`
      );
      const toGroupId = select?.value || '';
      placePerson(id, toGroupId, action === 'move-to' ? 'move' : 'add', group);
    }
  });

  groupsEl.addEventListener('change', (e) => {
    const select = e.target.closest('select[data-action="set-status"]');
    if (!select) return;
    setGroupStatus(select.getAttribute('data-group'), select.value);
  });

  groupsEl.addEventListener('input', (e) => {
    const input = e.target.closest('input[data-action="vote-note"]');
    if (!input || !canVote) return;
    queueVoteNote(input.getAttribute('data-id'), input.getAttribute('data-group'), input.value);
  });

  groupsEl.addEventListener('change', (e) => {
    const input = e.target.closest('input[data-action="vote-note"]');
    if (!input || !canVote) return;
    clearTimeout(voteNoteTimer);
    setVoteNote(input.getAttribute('data-id'), input.getAttribute('data-group'), input.value);
  });

  document.getElementById('gov-pool-body')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="add"]');
    if (!btn || (!canShape && !canPropose)) return;
    const id = btn.getAttribute('data-id');
    const select = document.querySelector(`[data-add-select="${CSS.escape(id)}"]`);
    if (select) addToGroup(id, select.value);
  });

  document.getElementById('gov-add-person')?.addEventListener('click', (e) => {
    e.preventDefault();
    addNewPerson();
  });

  document.getElementById('gov-create-group')?.addEventListener('click', (e) => {
    e.preventDefault();
    createGroup();
  });

  document.getElementById('gov-export')?.addEventListener('click', () => {
    const blob = new Blob(
      [
        JSON.stringify(
          {
            version: 3,
            order: state.order,
            membership: effectiveMembership(),
            people,
            myVotes,
            tallies,
          },
          null,
          2
        ),
      ],
      { type: 'application/json' }
    );
    downloadBlob(blob, `governance-membership-${exportStamp()}.json`);
    setStatus('Exported JSON');
  });

  const exportStamp = () => new Date().toISOString().slice(0, 10);

  const downloadBlob = (blob, filename) => {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    URL.revokeObjectURL(a.href);
  };

  const csvEscape = (value) => {
    const s = String(value ?? '');
    if (/[",\n\r]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
    return s;
  };

  /** Flat rows for CSV / PDF tables */
  const buildExportRows = () => {
    const rows = [];
    groupOrder.forEach((groupId) => {
      const label = groupMeta[groupId]?.label || groupId;
      const blurb = groupMeta[groupId]?.blurb || '';
      ensureOrder(groupId);
      const ids = state.order[groupId] || sortedIdsForGroup(groupId);
      ids.forEach((id) => {
        const person = personById(id);
        if (!person) return;
        const t = getTally(id, groupId);
        const other = groupsFor(person)
          .filter((g) => g !== groupId)
          .map((g) => groupMeta[g]?.label || g)
          .join('; ');
        rows.push({
          groupId,
          groupLabel: label,
          groupBlurb: blurb,
          personId: id,
          name: person.name,
          role: person.role || '',
          yes: t.yes,
          maybe: t.maybe,
          no: t.no,
          total: t.total,
          myVote: getMyVote(id, groupId),
          alsoOn: other,
        });
      });
    });
    return rows;
  };

  const exportCsv = () => {
    const rows = buildExportRows();
    const headers = [
      'Group',
      'Name',
      'Role',
      'Yes',
      'Maybe',
      'No',
      'Total opinions',
      'My mark',
      'Also on',
    ];
    const lines = [headers.map(csvEscape).join(',')];
    rows.forEach((r) => {
      lines.push(
        [
          r.groupLabel,
          r.name,
          r.role,
          r.yes,
          r.maybe,
          r.no,
          r.total,
          r.myVote === 'unset' ? '' : r.myVote,
          r.alsoOn,
        ]
          .map(csvEscape)
          .join(',')
      );
    });
    // UTF-8 BOM so Excel on Windows recognises encoding
    const blob = new Blob(['\ufeff' + lines.join('\r\n')], {
      type: 'text/csv;charset=utf-8',
    });
    downloadBlob(blob, `governance-membership-${exportStamp()}.csv`);
    setStatus('Exported CSV — opens in Excel');
  };

  const pdfFormalBlurb = (groupId, meta) => {
    const formal = {
      'project-board':
        'The Project Board provides senior oversight of the Housing Digital Transformation programme. It is responsible for strategic direction, prioritisation, and decisions on investment, resources, and escalation.',
      'steering-group':
        'The Steering Group brings together service leadership to review progress, surface operational issues, and advise the Project Board so that decisions are informed by service-wide knowledge.',
      'technical-group':
        'The Technical Group provides specialist advice on information technology, systems architecture, and data. It meets as required when technical detail is needed to support design or delivery decisions.',
      'business-group':
        'The Business Group draws on specialist expertise from across the Council. It meets as required to inform business requirements and the impact of change on services.',
    };
    if (formal[groupId]) return formal[groupId];
    const label = meta?.label || 'This group';
    return `${label} is a working group within the Housing Digital Transformation programme. Membership is recorded here for coordination and reference.`;
  };

  const formatAsOfDate = (date = new Date()) => {
    const day = date.getDate();
    const ordinal = (n) => {
      const v = n % 100;
      if (v >= 11 && v <= 13) return `${n}th`;
      const map = { 1: 'st', 2: 'nd', 3: 'rd' };
      return `${n}${map[n % 10] || 'th'}`;
    };
    const month = date.toLocaleDateString('en-GB', { month: 'long' });
    const year = date.getFullYear();
    return `As of the ${ordinal(day)} of ${month} ${year}`;
  };

  const buildPdfHtml = (options = {}) => {
    const snapshotGroups = Array.isArray(options.groups) ? options.groups : null;
    const asOf =
      options.asOfLabel ||
      formatAsOfDate(options.asOfDate instanceof Date ? options.asOfDate : new Date());
    const subtitle = options.subtitle ? `<p class="gov-pdf-meta">${esc(options.subtitle)}</p>` : '';

    let sections = '';
    if (snapshotGroups) {
      sections = snapshotGroups
        .map((g) => {
          const members = Array.isArray(g.members) ? g.members : [];
          const bodyRows = members
            .map(
              (m) => `<tr>
              <td class="gov-pdf-name">${esc(m.name)}</td>
              <td>${esc(m.role || '—')}</td>
              <td class="gov-pdf-also">—</td>
            </tr>`
            )
            .join('');
          const table = members.length
            ? `<table class="gov-pdf-table">
              <caption class="gov-pdf-caption">${esc(g.label || g.id)}</caption>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Role</th>
                  <th>Also on</th>
                </tr>
              </thead>
              <tbody>${bodyRows}</tbody>
            </table>`
            : `<p class="gov-pdf-empty">No members on this list.</p>`;
          const blurb =
            g.blurb ||
            pdfFormalBlurb(g.id, { label: g.label || g.id, blurb: g.blurb || '' });
          return `<section class="gov-pdf-group">
          <header class="gov-pdf-group-head">
            <p class="gov-pdf-kicker">${members.length} ${members.length === 1 ? 'person' : 'people'}${
              g.status ? ` · ${esc(statusLabel(g.status))}` : ''
            }</p>
            <h2>${esc(g.label || g.id)}</h2>
            <p class="gov-pdf-blurb">${esc(blurb)}</p>
          </header>
          ${table}
        </section>`;
        })
        .join('');
    } else {
      sections = groupOrder
        .map((groupId) => {
          const meta = groupMeta[groupId] || { label: groupId, blurb: '' };
          ensureOrder(groupId);
          const ids = state.order[groupId] || sortedIdsForGroup(groupId);
          const members = ids.map((id) => personById(id)).filter(Boolean);
          const bodyRows = members
            .map((p) => {
              const also = groupsFor(p)
                .filter((g) => g !== groupId)
                .map((g) => groupMeta[g]?.label || g)
                .join(', ');
              return `<tr>
              <td class="gov-pdf-name">${esc(p.name)}</td>
              <td>${esc(p.role || '—')}</td>
              <td class="gov-pdf-also">${also ? esc(also) : '—'}</td>
            </tr>`;
            })
            .join('');
          const table = members.length
            ? `<table class="gov-pdf-table">
              <caption class="gov-pdf-caption">${esc(meta.label || groupId)}</caption>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Role</th>
                  <th>Also on</th>
                </tr>
              </thead>
              <tbody>${bodyRows}</tbody>
            </table>`
            : `<p class="gov-pdf-empty">No members on this list.</p>`;
          return `<section class="gov-pdf-group">
          <header class="gov-pdf-group-head">
            <p class="gov-pdf-kicker">${members.length} ${members.length === 1 ? 'person' : 'people'}</p>
            <h2>${esc(meta.label || groupId)}</h2>
            <p class="gov-pdf-blurb">${esc(pdfFormalBlurb(groupId, meta))}</p>
          </header>
          ${table}
        </section>`;
        })
        .join('');
    }

    return `<div class="gov-pdf-doc">
      <header class="gov-pdf-cover">
        <p class="gov-pdf-eyebrow">East Renfrewshire Council — Housing Digital Transformation</p>
        <h1>Governance membership</h1>
        <p class="gov-pdf-meta">${esc(asOf)}</p>
        ${subtitle}
      </header>
      ${sections}
      <footer class="gov-pdf-foot">Point-in-time working list from the membership tool.</footer>
    </div>`;
  };

  const runPdfExport = (html, filename) => {
    const root = document.getElementById('gov-pdf-root');
    if (!root) return Promise.reject(new Error('PDF root missing'));
    if (typeof html2pdf === 'undefined') {
      return Promise.reject(new Error('PDF library did not load — check your network, then try again'));
    }
    const scrollX = window.scrollX || window.pageXOffset || 0;
    const scrollY = window.scrollY || window.pageYOffset || 0;
    // html2canvas blanks the top of pages when the window is scrolled; reset first.
    window.scrollTo(0, 0);

    root.innerHTML = html;
    root.hidden = false;
    const target = root.querySelector('.gov-pdf-doc');
    if (!target) {
      root.hidden = true;
      root.innerHTML = '';
      window.scrollTo(scrollX, scrollY);
      return Promise.reject(new Error('PDF document could not be built'));
    }

    const opt = {
      margin: [12, 12, 14, 12],
      filename,
      image: { type: 'jpeg', quality: 0.95 },
      html2canvas: {
        scale: 1.5,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff',
        scrollX: 0,
        scrollY: 0,
        windowWidth: target.scrollWidth || 720,
        onclone: (clonedDoc) => {
          const clonedRoot = clonedDoc.getElementById('gov-pdf-root');
          if (clonedRoot) {
            clonedRoot.style.opacity = '1';
            clonedRoot.style.position = 'static';
            clonedRoot.style.left = 'auto';
            clonedRoot.style.top = 'auto';
            clonedRoot.style.zIndex = '1';
            clonedRoot.removeAttribute('hidden');
          }
        },
      },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      // Start each group on a new page. Do NOT use avoid/inside — that parks
      // large tables at the foot with a blank band above.
      pagebreak: { mode: ['css'], before: '.gov-pdf-group' },
    };

    // html2pdf 0.10.x uses an es6-promise build that breaks on .finally() —
    // wrap .save() in a native Promise and clean up in then/catch only.
    return new Promise((resolve, reject) => {
      html2pdf()
        .set(opt)
        .from(target)
        .save()
        .then(() => {
          root.hidden = true;
          root.innerHTML = '';
          window.scrollTo(scrollX, scrollY);
          resolve();
        })
        .catch((err) => {
          root.hidden = true;
          root.innerHTML = '';
          window.scrollTo(scrollX, scrollY);
          reject(err);
        });
    });
  };

  const exportPdf = () => {
    const btn = document.getElementById('gov-export-pdf');
    const resetBtn = () => {
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Export PDF';
      }
    };
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Generating PDF…';
    }
    setStatus('Generating PDF…');
    runPdfExport(buildPdfHtml(), `governance-membership-${exportStamp()}.pdf`)
      .then(() => {
        setStatus('PDF exported');
        resetBtn();
      })
      .catch((err) => {
        console.error(err);
        setStatus(err.message || 'PDF export failed — try again', true);
        resetBtn();
      });
  };

  document.getElementById('gov-export-csv')?.addEventListener('click', exportCsv);
  document.getElementById('gov-export-pdf')?.addEventListener('click', exportPdf);

  document.getElementById('gov-reset-all')?.addEventListener('click', () => {
    if (!canVote) return;
    if (
      !confirm(
        'Clear only your Yes / Maybe / No marks across all groups? Other people’s opinions stay. Membership lists are unchanged.'
      )
    ) {
      return;
    }
    setStatus('Clearing your marks…');
    postJson({ action: 'clear_my_votes' })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus('Your marks were cleared');
        render();
      })
      .catch((err) => setStatus(err.message || 'Could not clear marks', true));
  });

  const formatHistoryWhen = (iso) => {
    const d = new Date(String(iso).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(iso);
    return d.toLocaleString('en-GB', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const reasonLabel = (reason) => HISTORY_REASONS[reason] || reason || 'Change';

  const updateHistoryChrome = () => {
    const countEl = document.getElementById('gov-history-count');
    const moreBtn = document.getElementById('gov-history-more');
    if (countEl) {
      if (historyTotal < 1) {
        countEl.hidden = true;
        countEl.textContent = '';
      } else {
        countEl.hidden = false;
        const shown = historyEntries.length;
        const noun = historyTotal === 1 ? 'entry' : 'entries';
        countEl.textContent =
          shown < historyTotal
            ? `Showing ${shown} of ${historyTotal} ${noun}`
            : `${historyTotal} ${noun}`;
      }
    }
    if (moreBtn) {
      moreBtn.hidden = !historyHasMore;
      moreBtn.disabled = historyLoading || !historyHasMore;
      moreBtn.textContent = historyLoading ? 'Loading…' : 'Show older';
    }
    document.querySelectorAll('[data-history-filter]').forEach((btn) => {
      const on = btn.getAttribute('data-history-filter') === historyFilter;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  };

  const renderHistoryList = () => {
    const listEl = document.getElementById('gov-history-list');
    if (!listEl) return;
    updateHistoryChrome();
    if (!historyEntries.length) {
      listEl.innerHTML =
        historyFilter === 'milestones'
          ? '<li class="gov-history-empty-item">No checkpoints yet. Save a checkpoint to pin a milestone.</li>'
          : '<li class="gov-history-empty-item">No snapshots yet. Membership changes will appear here automatically.</li>';
      return;
    }
    listEl.innerHTML = historyEntries
      .map((h) => {
        const active = selectedHistoryId === h.id ? ' is-active' : '';
        const by = h.capturedBy ? ` · ${esc(h.capturedBy)}` : '';
        return `<li>
          <button type="button" class="gov-history-item${active}" data-history-id="${h.id}">
            <span class="gov-history-when">${esc(formatHistoryWhen(h.capturedAt))}</span>
            <span class="gov-history-reason">${esc(reasonLabel(h.reason))}${by}</span>
          </button>
        </li>`;
      })
      .join('');
  };


  const countSnapshotMembers = (entry) => {
    const groups = Array.isArray(entry?.snapshot?.groups) ? entry.snapshot.groups : [];
    return groups.reduce((n, g) => n + (Array.isArray(g.members) ? g.members.length : 0), 0);
  };

  const restoreHistoryEntry = (entry) => {
    if (!canConfirmMembership) {
      setStatus('Only a membership confirmer can restore a snapshot', true);
      return;
    }
    if (!entry?.id) return;
    const memberCount = countSnapshotMembers(entry);
    const when = formatHistoryWhen(entry.capturedAt);
    const ok = confirm(
      `Replace the live membership lists with the composition from ${when} (${memberCount} placements)?\n\nThis cannot be undone except by restoring another snapshot.`
    );
    if (!ok) return;
    setStatus('Restoring membership from history…');
    const btn = document.getElementById('gov-history-restore');
    if (btn) btn.disabled = true;
    postJson({ action: 'restore_history', historyId: entry.id, force: false })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        setStatus('Membership restored from history');
        render();
        refreshHistoryQuiet();
        openHistoryEntry(entry.id);
      })
      .catch((err) => {
        const msg = err.message || 'Could not restore snapshot';
        if (/far fewer|empty snapshot/i.test(msg)) {
          const force = confirm(`${msg}\n\nForce restore anyway?`);
          if (!force) {
            setStatus(msg, true);
            return;
          }
          return postJson({ action: 'restore_history', historyId: entry.id, force: true }).then((data) => {
            if (data.state) applyBundle(data.state);
            dirty = false;
            setStatus('Membership force-restored from history');
            render();
            refreshHistoryQuiet();
            openHistoryEntry(entry.id);
          });
        }
        setStatus(msg, true);
      })
      .catch((err) => setStatus(err.message || 'Could not restore snapshot', true))
      .finally(() => {
        if (btn) btn.disabled = false;
      });
  };

  const reseedUnassignedFromSeed = () => {
    if (!canConfirmMembership) {
      setStatus('Only a membership confirmer can reseed unassigned people', true);
      return;
    }
    const ok = confirm(
      'Place unassigned people back onto the groups listed in the seed file?\n\nPeople who are already on a list are left unchanged. Use this after an accidental wipe when history does not have a full snapshot.'
    );
    if (!ok) return;
    setStatus('Reseeding unassigned people…');
    const btn = document.getElementById('gov-history-reseed');
    if (btn) btn.disabled = true;
    postJson({ action: 'reseed_unassigned' })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        const n = data.reseeded?.restoredPeople ?? 0;
        const pairs = data.reseeded?.insertedPairs ?? 0;
        setStatus(
          n
            ? `Reseeded ${n} people (${pairs} placements) from the seed list`
            : 'No unassigned seed people needed restoring'
        );
        render();
        refreshHistoryQuiet();
      })
      .catch((err) => setStatus(err.message || 'Could not reseed unassigned people', true))
      .finally(() => {
        if (btn) btn.disabled = false;
      });
  };

  const SNAPSHOT_STATUS_FILTERS = [
    { id: 'all', label: 'All statuses' },
    { id: 'active', label: 'Active / stable' },
    { id: 'development', label: 'In development' },
    { id: 'inactive', label: 'Inactive' },
    { id: 'dissolved', label: 'Dissolved' },
  ];

  const snapshotGroupMatches = (g) => {
    const status = g.status || 'development';
    if (snapshotStatusFilter !== 'all' && status !== snapshotStatusFilter) {
      return false;
    }
    const q = snapshotSearchQuery.trim().toLowerCase();
    if (!q) return true;
    const hay = [
      g.label || '',
      g.id || '',
      g.blurb || '',
      ...((Array.isArray(g.members) ? g.members : []).flatMap((m) => [m.name || '', m.role || ''])),
    ]
      .join(' ')
      .toLowerCase();
    return hay.includes(q);
  };

  const filteredSnapshotGroups = (entry) => {
    const groups = Array.isArray(entry?.snapshot?.groups) ? entry.snapshot.groups : [];
    return groups.filter(snapshotGroupMatches);
  };

  const snapshotFilterNote = (entry) => {
    const total = Array.isArray(entry?.snapshot?.groups) ? entry.snapshot.groups.length : 0;
    const shown = filteredSnapshotGroups(entry).length;
    const bits = [];
    if (snapshotStatusFilter !== 'all') {
      bits.push(statusLabel(snapshotStatusFilter));
    }
    if (snapshotSearchQuery.trim()) {
      bits.push(`“${snapshotSearchQuery.trim()}”`);
    }
    if (!bits.length) return '';
    return `Focus: ${bits.join(' · ')} (${shown} of ${total} groups)`;
  };

  const exportHistoryPdf = (entry) => {
    if (!entry?.snapshot) {
      setStatus('Load a snapshot before exporting', true);
      return;
    }
    const when = formatHistoryWhen(entry.capturedAt);
    const by = entry.capturedBy ? ` by ${entry.capturedBy}` : '';
    const reason = reasonLabel(entry.reason);
    const asOfDate = new Date(String(entry.capturedAt).replace(' ', 'T'));
    const asOfLabel = Number.isNaN(asOfDate.getTime())
      ? `As of ${when}`
      : formatAsOfDate(asOfDate);
    const focus = snapshotFilterNote(entry);
    const subtitle = [`Snapshot · ${when}${by} · ${reason}`, focus].filter(Boolean).join(' · ');
    const stamp = String(entry.capturedAt).slice(0, 10).replace(/[^0-9-]/g, '') || exportStamp();
    const btn = document.getElementById('gov-history-export-pdf');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Generating PDF…';
    }
    setStatus('Generating snapshot PDF…');
    runPdfExport(
      buildPdfHtml({
        groups: filteredSnapshotGroups(entry),
        asOfLabel,
        subtitle,
        asOfDate,
      }),
      `governance-membership-as-of-${stamp}.pdf`
    )
      .then(() => {
        setStatus('Snapshot PDF exported');
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Export PDF';
        }
      })
      .catch((err) => {
        console.error(err);
        setStatus(err.message || 'PDF export failed — try again', true);
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Export PDF';
        }
      });
  };

  const renderHistorySnapshot = (entry) => {
    const viewEl = document.getElementById('gov-history-view');
    if (!viewEl) return;
    selectedHistoryEntry = entry;
    const allGroups = Array.isArray(entry?.snapshot?.groups) ? entry.snapshot.groups : [];
    const groups = filteredSnapshotGroups(entry);
    const when = formatHistoryWhen(entry.capturedAt);
    const by = entry.capturedBy ? ` by ${esc(entry.capturedBy)}` : '';
    const statusButtons = SNAPSHOT_STATUS_FILTERS.map((opt) => {
      const on = snapshotStatusFilter === opt.id ? ' is-active' : '';
      return `<button type="button" class="gov-history-filter${on}" data-snapshot-status="${esc(opt.id)}" aria-pressed="${
        snapshotStatusFilter === opt.id ? 'true' : 'false'
      }">${esc(opt.label)}</button>`;
    }).join('');
    const blocks = groups
      .map((g) => {
        const members = Array.isArray(g.members) ? g.members : [];
        const rows = members.length
          ? `<ul class="gov-history-members">${members
              .map(
                (m) =>
                  `<li><strong>${esc(m.name)}</strong><span class="gov-person-role">${esc(m.role || 'Role not recorded')}</span></li>`
              )
              .join('')}</ul>`
          : `<p class="gov-empty">No members at this time.</p>`;
        return `<article class="gov-history-group">
          <h3 class="gov-history-group-title">${esc(g.label || g.id)} <span class="gov-jump-count">${members.length}</span>${
            g.status
              ? ` <span class="gov-status-badge gov-status-badge--${esc(g.status)}">${esc(statusLabel(g.status))}</span>`
              : ''
          }</h3>
          ${g.blurb ? `<p class="gov-section-lede">${esc(g.blurb)}</p>` : ''}
          ${rows}
        </article>`;
      })
      .join('');
    let emptyMsg = 'No groups in this snapshot.';
    if (allGroups.length && !groups.length) {
      emptyMsg = 'No groups match this status / search focus. Clear the filters to see the full snapshot.';
    }
    const focusHint = snapshotFilterNote(entry);
    const restoreBtn = canConfirmMembership
      ? `<button type="button" class="gov-btn" id="gov-history-restore">Restore this composition</button>`
      : '';
    viewEl.innerHTML = `<div class="gov-history-snapshot">
      <div class="gov-history-snapshot-toolbar">
        <p class="gov-history-snapshot-meta"><strong>As of ${esc(when)}</strong>${by} · ${esc(reasonLabel(entry.reason))}</p>
        <div class="gov-history-snapshot-actions">
          ${restoreBtn}
          <button type="button" class="gov-btn gov-btn--muted" id="gov-history-export-pdf">Export PDF</button>
        </div>
      </div>
      <div class="gov-history-snapshot-focus">
        <div class="gov-history-filters" role="group" aria-label="Focus groups by status">
          ${statusButtons}
        </div>
        <label class="gov-history-search">
          <span class="visually-hidden">Search this snapshot</span>
          <input type="search" id="gov-history-snapshot-search" placeholder="Search name or group…" value="${esc(
            snapshotSearchQuery
          )}" autocomplete="off">
        </label>
      </div>
      ${focusHint ? `<p class="gov-history-focus-note">${esc(focusHint)}</p>` : ''}
      ${blocks || `<p class="gov-empty">${esc(emptyMsg)}</p>`}
    </div>`;
    document.getElementById('gov-history-export-pdf')?.addEventListener('click', () => {
      exportHistoryPdf(entry);
    });
    document.getElementById('gov-history-restore')?.addEventListener('click', () => {
      restoreHistoryEntry(entry);
    });
    viewEl.querySelectorAll('[data-snapshot-status]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const next = btn.getAttribute('data-snapshot-status') || 'all';
        if (next === snapshotStatusFilter) return;
        snapshotStatusFilter = next;
        renderHistorySnapshot(entry);
      });
    });
    const searchEl = document.getElementById('gov-history-snapshot-search');
    searchEl?.addEventListener('input', () => {
      snapshotSearchQuery = searchEl.value || '';
      renderHistorySnapshot(entry);
      const again = document.getElementById('gov-history-snapshot-search');
      if (again) {
        again.focus();
        const len = again.value.length;
        again.setSelectionRange(len, len);
      }
    });
  };

  const applyHistoryPage = (data, { append = false } = {}) => {
    const items = Array.isArray(data.history) ? data.history : [];
    historyEntries = append ? historyEntries.concat(items) : items;
    historyTotal = typeof data.total === 'number' ? data.total : historyEntries.length;
    historyHasMore = Boolean(data.hasMore);
    if (typeof data.filter === 'string' && data.filter) {
      historyFilter = data.filter;
    }
    renderHistoryList();
    const checkpointBtn = document.getElementById('gov-history-checkpoint');
    if (checkpointBtn) checkpointBtn.disabled = !canShape;
    const reseedBtn = document.getElementById('gov-history-reseed');
    if (reseedBtn) reseedBtn.disabled = !canConfirmMembership;
  };

  const loadHistoryList = ({ append = false } = {}) => {
    if (historyLoading) return Promise.resolve();
    historyLoading = true;
    updateHistoryChrome();
    const offset = append ? historyEntries.length : 0;
    const url = `${API_URL}?history=1&limit=${HISTORY_PAGE_SIZE}&offset=${offset}&filter=${encodeURIComponent(historyFilter)}`;
    return fetch(url, { credentials: 'same-origin' })
      .then(async (r) => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.error || 'Could not load history');
        return data;
      })
      .then((data) => {
        applyHistoryPage(data, { append });
      })
      .finally(() => {
        historyLoading = false;
        updateHistoryChrome();
      });
  };

  const setHistoryFilter = (filter) => {
    if (filter !== 'all' && filter !== 'milestones') return;
    if (filter === historyFilter) return;
    historyFilter = filter;
    selectedHistoryId = null;
    selectedHistoryEntry = null;
    snapshotStatusFilter = 'all';
    snapshotSearchQuery = '';
    const viewEl = document.getElementById('gov-history-view');
    if (viewEl) {
      viewEl.innerHTML =
        '<p class="gov-history-empty">Select a point on the timeline to see the groups as they were then.</p>';
    }
    loadHistoryList({ append: false }).catch((err) =>
      setStatus(err.message || 'Could not load history', true)
    );
  };

  const openHistoryEntry = (id) => {
    selectedHistoryId = id;
    renderHistoryList();
    const viewEl = document.getElementById('gov-history-view');
    if (viewEl) viewEl.innerHTML = '<p class="gov-history-empty">Loading…</p>';
    fetch(`${API_URL}?historyId=${encodeURIComponent(id)}`, { credentials: 'same-origin' })
      .then(async (r) => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.error || 'Could not load snapshot');
        return data;
      })
      .then((entry) => {
        renderHistorySnapshot(entry);
      })
      .catch((err) => {
        selectedHistoryEntry = null;
        if (viewEl) {
          viewEl.innerHTML = `<p class="gov-history-empty">${esc(err.message || 'Could not load snapshot')}</p>`;
        }
      });
  };

  document.getElementById('gov-history-list')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-history-id]');
    if (!btn) return;
    const id = Number(btn.getAttribute('data-history-id'));
    if (id) openHistoryEntry(id);
  });

  document.querySelector('.gov-history-filters')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-history-filter]');
    if (!btn) return;
    setHistoryFilter(btn.getAttribute('data-history-filter'));
  });

  document.getElementById('gov-history-more')?.addEventListener('click', () => {
    if (!historyHasMore || historyLoading) return;
    loadHistoryList({ append: true }).catch((err) =>
      setStatus(err.message || 'Could not load older history', true)
    );
  });

  document.getElementById('gov-history-reseed')?.addEventListener('click', () => {
    reseedUnassignedFromSeed();
  });

  document.getElementById('gov-history-checkpoint')?.addEventListener('click', () => {
    if (!canShape) {
      setStatus('Sign in to save a checkpoint', true);
      return;
    }
    setStatus('Saving checkpoint…');
    postJson({ action: 'capture_checkpoint' })
      .then((data) => {
        historyFilter = 'all';
        applyHistoryPage(
          {
            history: Array.isArray(data.history) ? data.history : historyEntries,
            total: typeof data.total === 'number' ? data.total : undefined,
            hasMore: Boolean(data.hasMore),
            filter: 'all',
          },
          { append: false }
        );
        // Prefer a full refresh so totals/filters stay accurate after a new row.
        return loadHistoryList({ append: false }).then(() => data);
      })
      .then((data) => {
        if (data.captured?.id) {
          openHistoryEntry(data.captured.id);
          setStatus('Checkpoint saved');
        } else {
          setStatus('Checkpoint saved');
        }
      })
      .catch((err) => setStatus(err.message || 'Could not save checkpoint', true));
  });

  const refreshHistoryQuiet = () => {
    loadHistoryList({ append: false }).catch(() => {});
  };

  fetch(API_URL, { credentials: 'same-origin' })
    .then(async (r) => {
      if (r.status === 401) {
        throw new Error('Sign in required — open this site with: php -S localhost:8080 router.php');
      }
      const data = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(data.error || 'Failed to load membership');
      return data;
    })
    .then((bundle) => {
      applyBundle(bundle);
      render();
      return loadHistoryList();
    })
    .then(() => {
      if (location.hash) {
        const el = document.querySelector(location.hash);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    })
    .catch((err) => {
      setStatus(err.message || 'Could not load membership data', true);
      groupsEl.innerHTML = `<p class="gov-empty">${esc(err.message || 'No membership data loaded.')}</p>`;
    });
})();
