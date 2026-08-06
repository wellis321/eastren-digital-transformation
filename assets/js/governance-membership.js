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
  const attrFilterEl = document.getElementById('gov-attr-filter');
  const attrChipsEl = document.getElementById('gov-attr-filter-chips');
  const groupChipsEl = document.getElementById('gov-group-filter-chips');
  const attrStatusEl = document.getElementById('gov-attr-filter-status');
  const poolSectionEl = document.getElementById('pool');
  const POOL_SCOPE = 'pool';

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
  let myVoteNoteShared = {};
  let sharedVoteNotes = {};
  let membershipMeta = {};
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
  let attributeLabels = {};
  /** @type {Record<string, {id:string,label:string,isSeed:boolean,sort:number}>} */
  let attributeMeta = {};
  /** @type {Set<string>} */
  let activeTagFilters = new Set();
  /** @type {Set<string>} group ids and/or POOL_SCOPE; empty = all lists */
  let activeGroupFilters = new Set();

  const orderedAttributeIds = () => {
    const ids = Object.keys(attributeMeta);
    if (!ids.length) return Object.keys(attributeLabels);
    return ids.sort((a, b) => {
      const sa = attributeMeta[a]?.sort ?? 9999;
      const sb = attributeMeta[b]?.sort ?? 9999;
      if (sa !== sb) return sa - sb;
      return (attributeMeta[a]?.label || a).localeCompare(attributeMeta[b]?.label || b, 'en-GB');
    });
  };

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
    attribute_create: 'Person type created',
    attribute_rename: 'Person type renamed',
    attribute_delete: 'Person type deleted',
    person_tags: 'Person types updated',
    restore: 'Restored from history',
    reseed_unassigned: 'Rebuilt membership from seed list',
    reapply_seed: 'Reapplied seed list placements',
    rebuild_seed: 'Rebuilt membership from seed list',
  };

  const setStatus = (msg, isError) => {
    if (!statusEl) return;
    statusEl.textContent = msg;
    statusEl.classList.toggle('gov-toolbar-note--error', !!isError);
  };

  const personById = (id) => people.find((p) => p.id === id);

  const personTags = (person) =>
    Array.isArray(person?.tags) ? person.tags.filter((t) => typeof t === 'string' && t) : [];

  const tagLabel = (id) => attributeLabels[id] || id;

  const personMatchesTagFilter = (person) => {
    if (!activeTagFilters.size) return true;
    const tags = personTags(person);
    for (const t of activeTagFilters) {
      if (tags.includes(t)) return true;
    }
    return false;
  };

  const groupFilterActive = () => activeGroupFilters.size > 0;

  const groupInScope = (groupId) => {
    if (!groupFilterActive()) return true;
    return activeGroupFilters.has(groupId);
  };

  const poolInScope = () => {
    if (!groupFilterActive()) return true;
    return activeGroupFilters.has(POOL_SCOPE);
  };

  /** People considered when counting type chips under the current group scope. */
  const peopleInGroupScope = () => {
    if (!groupFilterActive()) return people;
    return people.filter((p) => {
      const gs = groupsFor(p);
      if (!gs.length) return activeGroupFilters.has(POOL_SCOPE);
      return gs.some((g) => activeGroupFilters.has(g));
    });
  };

  const filteredPeople = (list) => list.filter(personMatchesTagFilter);

  const tagsInUse = (scopePeople = people) => {
    const counts = new Map();
    for (const p of scopePeople) {
      for (const t of personTags(p)) {
        counts.set(t, (counts.get(t) || 0) + 1);
      }
    }
    return counts;
  };

  const renderTagBadges = (person) => {
    const tags = personTags(person);
    if (!tags.length) return '';
    return `<ul class="gov-person-tags" aria-label="Person types">${tags
      .map((t) => {
        const on = activeTagFilters.has(t) ? ' is-active' : '';
        return `<li><button type="button" class="gov-person-tag${on}" data-action="toggle-tag" data-tag="${esc(t)}" title="Filter by ${esc(tagLabel(t))}">${esc(tagLabel(t))}</button></li>`;
      })
      .join('')}</ul>`;
  };

  const renderPersonTagEditor = (person) => {
    if (!canShape) return '';
    const tags = new Set(personTags(person));
    const ids = orderedAttributeIds();
    if (!ids.length) {
      return `<details class="gov-person-tag-edit">
        <summary>Edit types</summary>
        <p class="gov-place-note">No person types yet — create one under Person types in the sidebar.</p>
      </details>`;
    }
    const checks = ids
      .map((id) => {
        const checked = tags.has(id) ? ' checked' : '';
        return `<label class="gov-tag-check"><input type="checkbox" value="${esc(id)}"${checked}> ${esc(tagLabel(id))}</label>`;
      })
      .join('');
    return `<details class="gov-person-tag-edit">
      <summary>Edit types</summary>
      <div class="gov-tag-check-grid" data-tag-editor="${esc(person.id)}">${checks}</div>
      <button type="button" class="gov-btn gov-btn--muted" data-action="save-person-tags" data-id="${esc(person.id)}">Save types</button>
    </details>`;
  };

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

  /** Default to shared when no preference is stored yet (new notes are meant to be readable). */
  const getMyVoteNoteShared = (personId, groupId) => {
    const flag = myVoteNoteShared?.[personId]?.[groupId];
    return flag === false || flag === 0 || flag === '0' ? false : true;
  };

  const getSharedVoteNotes = (personId, groupId) => {
    const list = sharedVoteNotes?.[personId]?.[groupId];
    return Array.isArray(list) ? list : [];
  };

  /** Who last placed/moved this person onto this group, and when — from governance_membership.updated_by/at. */
  const getMembershipMeta = (personId, groupId) => {
    const meta = membershipMeta?.[personId]?.[groupId];
    return meta && meta.by ? meta : null;
  };

  /** MySQL "YYYY-MM-DD HH:MM:SS" -> "6 Aug 2026". */
  const formatShortDate = (mysqlDateTime) => {
    if (!mysqlDateTime) return '';
    const d = new Date(mysqlDateTime.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
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
    myVoteNoteShared = asPlainObject(bundle.myVoteNoteShared);
    sharedVoteNotes = asPlainObject(bundle.sharedVoteNotes);
    membershipMeta = asPlainObject(bundle.membershipMeta);
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
    if (bundle.attributeLabels && typeof bundle.attributeLabels === 'object' && !Array.isArray(bundle.attributeLabels)) {
      attributeLabels = bundle.attributeLabels;
    }
    if (bundle.attributes && typeof bundle.attributes === 'object' && !Array.isArray(bundle.attributes)) {
      attributeMeta = bundle.attributes;
      const fromMeta = {};
      Object.keys(attributeMeta).forEach((id) => {
        fromMeta[id] = attributeMeta[id].label || id;
      });
      attributeLabels = { ...attributeLabels, ...fromMeta };
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
    myVoteNoteShared = asPlainObject(myVoteNoteShared);
    if (vote === 'unset') {
      delete myVotes[personId][groupId];
      if (!Object.keys(myVotes[personId]).length) delete myVotes[personId];
      if (myVoteNotes[personId]) {
        delete myVoteNotes[personId][groupId];
        if (!Object.keys(myVoteNotes[personId]).length) delete myVoteNotes[personId];
      }
      if (myVoteNoteShared[personId]) {
        delete myVoteNoteShared[personId][groupId];
        if (!Object.keys(myVoteNoteShared[personId]).length) delete myVoteNoteShared[personId];
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
  // Guards against out-of-order responses: an older in-flight save resolving after a
  // newer one must not redraw the list with its now-stale (e.g. shorter) text.
  const voteNoteSeq = {};

  // render() rebuilds the whole list, which destroys and recreates the reason field —
  // silently stealing focus (and any keystrokes typed right after) if the user is still
  // in it. Capture what's focused, redraw, then refocus the matching field.
  const captureVoteNoteFocus = () => {
    const el = document.activeElement;
    if (!el || !groupsEl.contains(el)) return null;
    const action = el.getAttribute('data-action');
    if (action !== 'vote-note' && action !== 'vote-note-share') return null;
    return {
      action,
      id: el.getAttribute('data-id'),
      group: el.getAttribute('data-group'),
      selectionStart: typeof el.selectionStart === 'number' ? el.selectionStart : null,
      selectionEnd: typeof el.selectionEnd === 'number' ? el.selectionEnd : null,
    };
  };
  const restoreVoteNoteFocus = (focusInfo) => {
    if (!focusInfo) return;
    const candidates = groupsEl.querySelectorAll(`[data-action="${focusInfo.action}"]`);
    for (const el of candidates) {
      if (el.getAttribute('data-id') !== focusInfo.id || el.getAttribute('data-group') !== focusInfo.group) {
        continue;
      }
      el.focus();
      if (focusInfo.selectionStart !== null && typeof el.setSelectionRange === 'function') {
        try {
          el.setSelectionRange(focusInfo.selectionStart, focusInfo.selectionEnd);
        } catch {
          // some input types don't support selection ranges — ignore
        }
      }
      break;
    }
  };

  const setVoteNote = (personId, groupId, comment, shared, { quiet = false } = {}) => {
    if (!canVote) return;
    if (getMyVote(personId, groupId) === 'unset') {
      if (!quiet) setStatus('Choose Yes / Maybe / No before adding a reason', true);
      return;
    }
    const share = shared !== false;
    myVoteNotes = asPlainObject(myVoteNotes);
    myVoteNoteShared = asPlainObject(myVoteNoteShared);
    if (!myVoteNotes[personId]) myVoteNotes[personId] = {};
    if (!myVoteNoteShared[personId]) myVoteNoteShared[personId] = {};
    const trimmed = String(comment || '').trim().slice(0, 500);
    myVoteNoteShared[personId][groupId] = share;
    if (trimmed) myVoteNotes[personId][groupId] = trimmed;
    else {
      delete myVoteNotes[personId][groupId];
      if (!Object.keys(myVoteNotes[personId]).length) delete myVoteNotes[personId];
    }
    if (!quiet) setStatus(share ? 'Saving shared reason…' : 'Saving private reason…');
    const fieldKey = `${personId}::${groupId}`;
    const seq = (voteNoteSeq[fieldKey] = (voteNoteSeq[fieldKey] || 0) + 1);
    postJson({
      action: 'set_vote_note',
      personId,
      groupId,
      comment: trimmed,
      shared: share,
    })
      .then((data) => {
        if (seq !== voteNoteSeq[fieldKey]) return; // a newer save has since started — drop this stale response
        const focusInfo = captureVoteNoteFocus();
        if (data.state) applyBundle(data.state);
        if (!quiet) {
          setStatus(
            share
              ? 'Reason saved — others can see it with your mark'
              : 'Reason saved — only you can see it'
          );
        }
        render();
        restoreVoteNoteFocus(focusInfo);
      })
      .catch((err) => {
        if (seq !== voteNoteSeq[fieldKey]) return;
        setStatus(err.message || 'Could not save note', true);
      });
  };

  const queueVoteNote = (personId, groupId, comment, shared) => {
    clearTimeout(voteNoteTimer);
    voteNoteTimer = setTimeout(
      () => setVoteNote(personId, groupId, comment, shared, { quiet: true }),
      450
    );
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
              ? `Moved to ${label}`
              : `Also added to ${label}`
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

  // Case/whitespace-insensitive lookup by name — catches "Alan Scott" vs "alan scott ".
  // Returns the matched item itself (not just its name) so callers can show more context,
  // e.g. a person's role, to help judge whether it's actually the same person.
  const findDuplicateByName = (value, items, getName) => {
    const needle = value.trim().toLowerCase();
    if (!needle) return null;
    return items.find((item) => getName(item).trim().toLowerCase() === needle) || null;
  };

  /** "They're on Steering Group, Technical Group.", or a fallback when unassigned. */
  const describeMatchGroups = (person) => {
    const labels = groupsFor(person).map((g) => groupMeta[g]?.label || g);
    return labels.length ? `They're on ${labels.join(', ')}.` : 'They are not on any group yet.';
  };

  // Live-warn under a name field as the user types, so duplicates get caught before
  // they hit "Add" — the confirm() in each create function is the second, final check.
  const wireDuplicateWarning = (inputId, warnId, getExistingItems, getName, describeMatch) => {
    const input = document.getElementById(inputId);
    const warn = document.getElementById(warnId);
    if (!input || !warn) return;
    input.addEventListener('input', () => {
      const match = findDuplicateByName(input.value, getExistingItems(), getName);
      if (match) {
        warn.textContent = describeMatch(match);
        warn.hidden = false;
      } else {
        warn.hidden = true;
      }
    });
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
    const dupe = findDuplicateByName(name, people, (p) => p.name);
    if (
      dupe &&
      !confirm(
        `A person named “${dupe.name}” already exists. They have the role of “${dupe.role || 'not recorded'}”. ${describeMatchGroups(dupe)}\n\n` +
          `If that's the same person, choose Cancel and use “Also on” from their existing card to add them ` +
          `to this group instead.\n\n` +
          `If they're a different person, continue to add “${name}” as usual.\n\n` +
          `Add “${name}” anyway?`
      )
    ) {
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
        const nameDupeWarn = document.getElementById('gov-new-name-dupe');
        if (nameDupeWarn) nameDupeWarn.hidden = true;
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
    const dupe = findDuplicateByName(label, Object.values(groupMeta), (g) => g.label);
    if (dupe && !confirm(`A group named “${dupe.label}” already exists. Create “${label}” as a separate group anyway?`)) {
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
        const labelDupeWarn = document.getElementById('gov-new-group-label-dupe');
        if (labelDupeWarn) labelDupeWarn.hidden = true;
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
    // Only show the marks that actually happened — "0Y 1~ 0N" made people
    // decode zeros and single-letter codes just to find the one real number.
    const parts = [];
    if (t.yes) parts.push(`<span class="gov-tally-compact__yes">${t.yes} Yes</span>`);
    if (t.maybe) parts.push(`<span class="gov-tally-compact__maybe">${t.maybe} Maybe</span>`);
    if (t.no) parts.push(`<span class="gov-tally-compact__no">${t.no} No</span>`);
    const sep = '<span class="gov-tally-compact__sep"> · </span>';
    return `<span class="gov-tally-compact" title="${t.yes} Yes · ${t.maybe} Maybe · ${t.no} No">${parts.join(sep)}</span>`;
  };

  const renderSharedNotes = (personId, groupId) => {
    const notes = getSharedVoteNotes(personId, groupId);
    if (!notes.length) return '';
    const items = notes
      .map((n) => {
        const author = n.mine ? 'You' : esc(n.author || 'Someone');
        const voteLabel =
          n.vote === 'yes' ? 'Yes' : n.vote === 'maybe' ? 'Maybe' : n.vote === 'no' ? 'No' : '';
        const voteBit = voteLabel ? ` · ${voteLabel}` : '';
        return `<li><span class="gov-shared-note__who">${author}${voteBit}:</span> ${esc(n.note)}</li>`;
      })
      .join('');
    return `<ul class="gov-shared-notes" aria-label="Shared reasons">${items}</ul>`;
  };

  const renderRow = (person, groupId) => {
    const vote = getMyVote(person.id, groupId);
    const voteDisabled = canVote ? '' : ' disabled';
    const noteShared = getMyVoteNoteShared(person.id, groupId);
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

    // Reason editing moves into a native popover, opened by a small trigger —
    // was a permanently-open textarea+checkbox under every voted person, for
    // every viewer, which was most of the page's density. Existing SHARED
    // reasons still render as plain read-only text below (renderSharedNotes),
    // unaffected — only the EDITING surface is now on-demand.
    let reasonBlock = '';
    if (canVote && vote !== 'unset') {
      const reasonPopId = `reason-pop-${esc(groupId)}-${esc(person.id)}`;
      const hasNote = getMyVoteNote(person.id, groupId).trim() !== '';
      reasonBlock = `<button type="button" class="gov-reason-trigger" popovertarget="${reasonPopId}">${hasNote ? 'Edit reason' : 'Add reason'}</button>
        <div class="gov-reason-popover" popover id="${reasonPopId}">
          <div class="gov-vote-note">
            <label class="gov-vote-note-field">
              <span class="gov-reason-popover-title">Your reason for ${esc(person.name)}</span>
              <textarea class="gov-vote-note-input" maxlength="500" rows="3" placeholder="Reason (optional)…" data-action="vote-note" data-id="${esc(person.id)}" data-group="${esc(groupId)}"${voteDisabled}>${esc(getMyVoteNote(person.id, groupId))}</textarea>
            </label>
            <label class="gov-vote-note-share">
              <input type="checkbox" data-action="vote-note-share" data-id="${esc(person.id)}" data-group="${esc(groupId)}"${noteShared ? ' checked' : ''}${voteDisabled}>
              Share with others
            </label>
          </div>
        </div>`;
    }

    // Tags, "also on", and place-on-another-group are real but infrequent —
    // tucked behind one "More" disclosure instead of always rendering.
    const tagCount = personTags(person).length;
    const tagBadges = renderTagBadges(person);
    const tagEditor = renderPersonTagEditor(person);
    const placeControls = renderPlaceControls(person, groupId);
    const placedMeta = getMembershipMeta(person.id, groupId);
    const placedLine = placedMeta
      ? `<p class="gov-placed-meta">On this list: ${esc(placedMeta.by)}${
          placedMeta.at ? ` · ${esc(formatShortDate(placedMeta.at))}` : ''
        }</p>`
      : '';
    const moreBody = `${placedLine}${tagBadges}${tagEditor}${alsoOn}${placeControls}`;
    const moreHintParts = [];
    if (tagCount) moreHintParts.push(`${tagCount} type${tagCount === 1 ? '' : 's'}`);
    if (otherLists.length) moreHintParts.push(`also on ${otherLists.length}`);
    const moreHint = moreHintParts.length ? ` <span class="gov-more-hint">${esc(moreHintParts.join(' · '))}</span>` : '';
    const moreBlock = moreBody
      ? `<details class="gov-person-more"><summary>More${moreHint}</summary><div class="gov-person-more-body">${moreBody}</div></details>`
      : '';

    return `<li class="gov-person-row${rowClass}" id="${rowId}" data-id="${esc(person.id)}" data-vote="${esc(vote)}">
      <div class="gov-person-top">
        <div class="gov-person-main">
          <div class="gov-person-icons" aria-hidden="true">${voteIcon(vote)}</div>
          <div class="gov-person-text">
            <div class="gov-person-line">
              <strong class="gov-person-name">${esc(person.name)}</strong>
              ${tentativeBadge}
              ${renderTallyCompact(person.id, groupId)}
            </div>
            <span class="gov-person-role">${esc(person.role || 'Role not recorded')}</span>
            ${renderSharedNotes(person.id, groupId)}
            ${prop ? `<p class="gov-proposal-meta">Suggested by ${esc(prop.proposedBy)}</p>${renderProposalActions(prop)}` : ''}
          </div>
        </div>
        <div class="gov-person-actions">
          <div class="gov-vote-btns" role="group" aria-label="Your mark for ${esc(person.name)}">
            <button type="button" class="gov-vote-btn${vote === 'yes' ? ' is-active' : ''}" data-action="vote" data-vote="yes" data-id="${esc(person.id)}" data-group="${esc(groupId)}"${voteDisabled}>Yes</button>
            <button type="button" class="gov-vote-btn${vote === 'maybe' ? ' is-active' : ''}" data-action="vote" data-vote="maybe" data-id="${esc(person.id)}" data-group="${esc(groupId)}"${voteDisabled}>Maybe</button>
            <button type="button" class="gov-vote-btn${vote === 'no' ? ' is-active' : ''}" data-action="vote" data-vote="no" data-id="${esc(person.id)}" data-group="${esc(groupId)}"${voteDisabled}>No</button>
            <button type="button" class="gov-vote-btn gov-vote-btn--clear" data-action="vote" data-vote="unset" data-id="${esc(person.id)}" data-group="${esc(groupId)}" title="Clear your mark"${voteDisabled}>Clear</button>
          </div>
          ${reasonBlock}
          ${removeBtn}
        </div>
      </div>
      ${moreBlock}
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
    const visibleMembers = filteredPeople(members);
    const groupProps = proposalsForGroup(groupId);
    const pendingAdds = groupProps.filter((p) => p.action === 'add' || p.action === 'move');
    const rows = visibleMembers.map((p) => renderRow(p, groupId)).join('');
    const tentativeRows = pendingAdds.map(renderTentativeAddRow).join('');
    const count = members.length;
    const visibleCount = visibleMembers.length;
    const suggestNote = pendingAdds.length
      ? ` · ${pendingAdds.length} suggested`
      : '';
    const filterNote =
      (activeTagFilters.size || groupFilterActive()) && visibleCount !== count
        ? ` · showing ${visibleCount}`
        : '';
    const countLabel = count === 1 ? '1 person' : `${count} people`;
    const empty =
      !ids.length && !pendingAdds.length
        ? `<p class="gov-empty">No members yet — add someone, move them here, or use the unassigned pool.</p>`
        : activeTagFilters.size && !visibleMembers.length && ids.length
          ? `<p class="gov-empty">No members match the selected person type${activeTagFilters.size > 1 ? 's' : ''}.</p>`
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
      visibleMembers.length || pendingAdds.length
        ? `<h3 class="gov-assess-heading">Members</h3><ul class="gov-person-list">${rows}${tentativeRows}</ul>`
        : '';

    return `<section class="gov-group-panel${customClass}${dissolvedClass}" id="${esc(groupId)}">
      <header class="gov-group-head">
        <p class="gov-group-kicker">${esc(countLabel)}${esc(filterNote)}${esc(suggestNote)}${customBadge}${statusBadge}</p>
        <h2 class="gov-group-title">${esc(meta.label || groupId)}</h2>
        ${meta.blurb ? `<p class="gov-group-blurb">${esc(meta.blurb)}</p>` : ''}
        <div class="gov-group-actions">
          ${statusRow}
          ${toolsHtml}
        </div>
      </header>
      ${empty}
      ${listHtml}
    </section>`;
  };

  const renderAwaiting = () => {
    // Assessment queue retired — panel removed from the page.
    if (!awaitingEl) return;
    awaitingEl.innerHTML = '';
  };

  const renderJump = () => {
    if (!jumpEl) return;
    const poolCount = unassignedPeople().length;
    const visibleGroups = groupOrder.filter(groupInScope);
    const links = [
      ...visibleGroups.map((g) => {
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
      poolInScope()
        ? `<a href="#pool">Unassigned <span class="gov-jump-count">${poolCount}</span></a>`
        : '',
      `<a href="#group-history">History</a>`,
    ].filter(Boolean);
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
    if (poolSectionEl) poolSectionEl.hidden = !poolInScope();
    if (!poolInScope()) {
      poolEl.innerHTML = '';
      return;
    }
    const allUnassigned = unassignedPeople();
    const list = filteredPeople(allUnassigned);
    if (!allUnassigned.length) {
      poolEl.innerHTML = `<p class="gov-empty">Everyone is assigned to at least one group.</p>`;
      return;
    }
    if (!list.length) {
      poolEl.innerHTML = `<p class="gov-empty">No unassigned people match the selected person type${activeTagFilters.size > 1 ? 's' : ''}.</p>`;
      return;
    }
    const groupOpts = groupOrder
      .map((g) => `<option value="${esc(g)}">${esc(groupMeta[g]?.label || g)}</option>`)
      .join('');
    poolEl.innerHTML = `<ul class="gov-pool-list">${list
      .map((p) => {
        const add =
          canShape || canPropose
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
            ${renderTagBadges(p)}
            ${renderPersonTagEditor(p)}
          </div>
          ${add}
        </li>`;
      })
      .join('')}</ul>`;
  };

  const renderAttrFilter = () => {
    if (!attrFilterEl || !attrChipsEl) return;
    const scopedPeople = peopleInGroupScope();
    const counts = tagsInUse(scopedPeople);
    if (!counts.size && !Object.keys(attributeMeta).length && !groupOrder.length) {
      attrFilterEl.hidden = true;
      return;
    }
    attrFilterEl.hidden = false;
    const ordered = orderedAttributeIds().filter((id) => counts.has(id) || activeTagFilters.has(id));
    for (const id of counts.keys()) {
      if (!ordered.includes(id)) ordered.push(id);
    }
    for (const id of activeTagFilters) {
      if (!ordered.includes(id)) ordered.push(id);
    }
    const clearOn = activeTagFilters.size === 0 ? ' is-active' : '';
    attrChipsEl.innerHTML = [
      `<button type="button" class="gov-attr-chip${clearOn}" data-action="clear-tag-filters" aria-pressed="${
        activeTagFilters.size === 0
      }">All types</button>`,
      ...ordered.map((id) => {
        const on = activeTagFilters.has(id) ? ' is-active' : '';
        const n = counts.get(id) || 0;
        return `<button type="button" class="gov-attr-chip${on}" data-action="toggle-tag" data-tag="${esc(
          id
        )}" aria-pressed="${activeTagFilters.has(id)}">${esc(tagLabel(id))} <span class="gov-jump-count">${n}</span></button>`;
      }),
    ].join('');

    if (groupChipsEl) {
      const groupClearOn = !groupFilterActive() ? ' is-active' : '';
      const groupBtns = groupOrder.map((g) => {
        const on = activeGroupFilters.has(g) ? ' is-active' : '';
        const n = membersForGroup(g).length;
        return `<button type="button" class="gov-attr-chip${on}" data-action="toggle-group-filter" data-group="${esc(
          g
        )}" aria-pressed="${activeGroupFilters.has(g)}">${esc(groupMeta[g]?.label || g)} <span class="gov-jump-count">${n}</span></button>`;
      });
      const poolOn = activeGroupFilters.has(POOL_SCOPE) ? ' is-active' : '';
      const poolCount = unassignedPeople().length;
      groupChipsEl.innerHTML = [
        `<button type="button" class="gov-attr-chip${groupClearOn}" data-action="clear-group-filters" aria-pressed="${
          !groupFilterActive()
        }">All groups</button>`,
        ...groupBtns,
        `<button type="button" class="gov-attr-chip${poolOn}" data-action="toggle-group-filter" data-group="${POOL_SCOPE}" aria-pressed="${activeGroupFilters.has(
          POOL_SCOPE
        )}">Unassigned <span class="gov-jump-count">${poolCount}</span></button>`,
      ].join('');
    }

    if (attrStatusEl) {
      if (!activeTagFilters.size && !groupFilterActive()) {
        attrStatusEl.hidden = true;
        attrStatusEl.textContent = '';
      } else {
        const typeBit = activeTagFilters.size
          ? activeTagFilters.size === 1
            ? tagLabel([...activeTagFilters][0])
            : `${activeTagFilters.size} types`
          : 'any type';
        let groupBit = 'all groups';
        if (groupFilterActive()) {
          const labels = [...activeGroupFilters].map((id) =>
            id === POOL_SCOPE ? 'Unassigned' : groupMeta[id]?.label || id
          );
          groupBit = labels.join(', ');
        }
        const match = scopedPeople.filter(personMatchesTagFilter).length;
        const denom = scopedPeople.length;
        attrStatusEl.hidden = false;
        attrStatusEl.textContent = `Showing ${match} of ${denom} people — ${typeBit} in ${groupBit}.`;
      }
    }
  };

  const renderAttrManageList = () => {
    const listEl = document.getElementById('gov-attr-manage-list');
    const createBtn = document.getElementById('gov-create-attr');
    const labelInput = document.getElementById('gov-new-attr-label');
    if (!listEl) return;
    if (createBtn) createBtn.disabled = !canShape;
    if (labelInput) labelInput.disabled = !canShape;
    const ids = orderedAttributeIds();
    if (!ids.length) {
      listEl.innerHTML = `<p class="gov-place-note">${canShape ? 'No types yet — create one below.' : 'No person types loaded.'}</p>`;
      return;
    }
    const counts = tagsInUse();
    listEl.innerHTML = `<ul class="gov-attr-manage-ul">${ids
      .map((id) => {
        const meta = attributeMeta[id] || { label: tagLabel(id), isSeed: false };
        const n = counts.get(id) || 0;
        const rename = canShape
          ? `<button type="button" class="gov-icon-btn" data-action="rename-attr" data-attr="${esc(id)}" title="Rename" aria-label="Rename ${esc(meta.label)}">✎</button>`
          : '';
        const del =
          canShape && !meta.isSeed
            ? `<button type="button" class="gov-icon-btn gov-icon-btn--danger" data-action="delete-attr" data-attr="${esc(id)}" title="Delete type" aria-label="Delete ${esc(meta.label)}">×</button>`
            : '';
        return `<li class="gov-attr-manage-row">
          <div class="gov-attr-manage-main">
            <strong>${esc(meta.label || id)}</strong>
            <span class="gov-jump-count">${n}</span>
          </div>
          <div class="gov-attr-manage-actions">${rename}${del}</div>
        </li>`;
      })
      .join('')}</ul>`;
  };

  const createAttribute = () => {
    if (!canShape) return;
    const labelInput = document.getElementById('gov-new-attr-label');
    const label = (labelInput?.value || '').trim();
    if (!label) {
      setStatus('Enter a name for the new person type', true);
      return;
    }
    const dupe = findDuplicateByName(label, Object.values(attributeMeta), (a) => a.label);
    if (dupe && !confirm(`A person type named “${dupe.label}” already exists. Create “${label}” as a separate type anyway?`)) {
      return;
    }
    setStatus('Creating person type…');
    postJson({ action: 'create_attribute', label })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        if (labelInput) labelInput.value = '';
        const attrDupeWarn = document.getElementById('gov-new-attr-label-dupe');
        if (attrDupeWarn) attrDupeWarn.hidden = true;
        setStatus(`Created type “${data.created?.label || label}” — assign it on people with Edit types`);
        render();
      })
      .catch((err) => setStatus(err.message || 'Could not create type', true));
  };

  const renameAttribute = (attrId) => {
    if (!canShape || !attrId) return;
    const current = attributeMeta[attrId]?.label || tagLabel(attrId);
    const next = prompt(`Rename person type “${current}”:`, current);
    if (next === null) return;
    const label = next.trim();
    if (!label) {
      setStatus('Name cannot be empty', true);
      return;
    }
    if (label === current) return;
    setStatus('Renaming…');
    postJson({ action: 'rename_attribute', attrId, label })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        setStatus(`Renamed to “${data.updated?.label || label}”`);
        render();
      })
      .catch((err) => setStatus(err.message || 'Could not rename type', true));
  };

  const deleteAttribute = (attrId) => {
    if (!canShape || !attrId) return;
    const meta = attributeMeta[attrId];
    if (!meta || meta.isSeed) {
      setStatus('Built-in person types cannot be deleted', true);
      return;
    }
    const n = tagsInUse().get(attrId) || 0;
    if (
      !confirm(
        `Delete type “${meta.label}”?${n ? ` It will be removed from ${n} people.` : ''} This cannot be undone.`
      )
    ) {
      return;
    }
    setStatus('Deleting type…');
    postJson({ action: 'delete_attribute', attrId })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        activeTagFilters.delete(attrId);
        setStatus(`Deleted “${meta.label}”`);
        render();
      })
      .catch((err) => setStatus(err.message || 'Could not delete type', true));
  };

  const savePersonTags = (personId) => {
    if (!canShape || !personId) return;
    const grid = document.querySelector(`[data-tag-editor="${CSS.escape(personId)}"]`);
    if (!grid) return;
    const tags = Array.from(grid.querySelectorAll('input[type="checkbox"]:checked')).map((el) => el.value);
    setStatus('Saving types…');
    postJson({ action: 'set_person_tags', personId, tags })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        setStatus('Person types updated');
        render();
      })
      .catch((err) => setStatus(err.message || 'Could not save types', true));
  };

  const toggleTagFilter = (tag) => {
    if (!tag) return;
    if (activeTagFilters.has(tag)) activeTagFilters.delete(tag);
    else activeTagFilters.add(tag);
    render();
  };

  const clearTagFilters = () => {
    if (!activeTagFilters.size) return;
    activeTagFilters.clear();
    render();
  };

  const toggleGroupFilter = (groupId) => {
    if (!groupId) return;
    if (groupId !== POOL_SCOPE && !groupOrder.includes(groupId)) return;
    if (activeGroupFilters.has(groupId)) activeGroupFilters.delete(groupId);
    else activeGroupFilters.add(groupId);
    render();
  };

  const clearGroupFilters = () => {
    if (!activeGroupFilters.size) return;
    activeGroupFilters.clear();
    render();
  };

  const render = () => {
    // Drop group filters for lists that no longer exist
    if (activeGroupFilters.size) {
      for (const id of [...activeGroupFilters]) {
        if (id !== POOL_SCOPE && !groupOrder.includes(id)) activeGroupFilters.delete(id);
      }
    }
    const visibleGroupIds = groupOrder.filter(groupInScope);
    if (visibleGroupIds.length) {
      groupsEl.innerHTML = visibleGroupIds.map(renderGroup).join('');
    } else if (groupFilterActive() && !poolInScope()) {
      groupsEl.innerHTML = `<p class="gov-empty">No groups selected — pick a group above, or choose All groups.</p>`;
    } else {
      groupsEl.innerHTML = '';
    }
    renderAwaiting();
    renderPool();
    renderJump();
    renderAttrFilter();
    renderAttrManageList();
    syncGroupSelects();
    const resetBtn = document.getElementById('gov-reset-all');
    const addBtn = document.getElementById('gov-add-person');
    const createBtn = document.getElementById('gov-create-group');
    if (resetBtn) resetBtn.disabled = !canVote;
    if (addBtn) addBtn.disabled = !canShape;
    if (createBtn) createBtn.disabled = !canShape;
    if (!dirty) {
      const who = currentUser.label ? ` as ${currentUser.label}` : '';
      setStatus(
        canVote
          ? `Ready${who} — move people freely; Yes / Maybe / No is optional`
          : `Signed in required to mark opinions`
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
    // Scoped to buttons only — a bare [data-action] selector also matches the reason
    // textarea/checkbox (data-action="vote-note"/"vote-note-share"), and calling
    // preventDefault() on those cancels the checkbox's native toggle before it can fire.
    const btn = e.target.closest('button[data-action]');
    if (!btn || btn.disabled) return;
    e.preventDefault();
    const action = btn.getAttribute('data-action');
    const id = btn.getAttribute('data-id');
    const group = btn.getAttribute('data-group');
    if (action === 'toggle-tag') {
      toggleTagFilter(btn.getAttribute('data-tag'));
      return;
    }
    if (action === 'save-person-tags') {
      savePersonTags(id);
      return;
    }
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

  attrFilterEl?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    e.preventDefault();
    const action = btn.getAttribute('data-action');
    if (action === 'clear-tag-filters') clearTagFilters();
    if (action === 'toggle-tag') toggleTagFilter(btn.getAttribute('data-tag'));
    if (action === 'clear-group-filters') clearGroupFilters();
    if (action === 'toggle-group-filter') toggleGroupFilter(btn.getAttribute('data-group'));
  });

  groupsEl.addEventListener('change', (e) => {
    const select = e.target.closest('select[data-action="set-status"]');
    if (!select) return;
    setGroupStatus(select.getAttribute('data-group'), select.value);
  });

  groupsEl.addEventListener('input', (e) => {
    const input = e.target.closest('[data-action="vote-note"]');
    if (!input || !canVote) return;
    const wrap = input.closest('.gov-vote-note');
    const shareEl = wrap?.querySelector('input[data-action="vote-note-share"]');
    const shared = shareEl ? shareEl.checked : true;
    queueVoteNote(input.getAttribute('data-id'), input.getAttribute('data-group'), input.value, shared);
  });

  groupsEl.addEventListener('change', (e) => {
    const shareBox = e.target.closest('input[data-action="vote-note-share"]');
    if (shareBox && canVote) {
      const wrap = shareBox.closest('.gov-vote-note');
      const noteInput = wrap?.querySelector('[data-action="vote-note"]');
      clearTimeout(voteNoteTimer);
      setVoteNote(
        shareBox.getAttribute('data-id'),
        shareBox.getAttribute('data-group'),
        noteInput?.value || '',
        shareBox.checked
      );
      return;
    }
    const input = e.target.closest('[data-action="vote-note"]');
    if (!input || !canVote) return;
    const wrap = input.closest('.gov-vote-note');
    const shareEl = wrap?.querySelector('input[data-action="vote-note-share"]');
    const shared = shareEl ? shareEl.checked : true;
    clearTimeout(voteNoteTimer);
    setVoteNote(input.getAttribute('data-id'), input.getAttribute('data-group'), input.value, shared);
  });

  document.getElementById('gov-pool-body')?.addEventListener('click', (e) => {
    const tagBtn = e.target.closest('[data-action="toggle-tag"]');
    if (tagBtn) {
      e.preventDefault();
      toggleTagFilter(tagBtn.getAttribute('data-tag'));
      return;
    }
    const saveBtn = e.target.closest('[data-action="save-person-tags"]');
    if (saveBtn) {
      e.preventDefault();
      savePersonTags(saveBtn.getAttribute('data-id'));
      return;
    }
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

  document.getElementById('gov-create-attr')?.addEventListener('click', (e) => {
    e.preventDefault();
    createAttribute();
  });

  document.getElementById('gov-new-attr-label')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      createAttribute();
    }
  });

  document.getElementById('gov-attr-manage-list')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn || !canShape) return;
    e.preventDefault();
    const action = btn.getAttribute('data-action');
    const attrId = btn.getAttribute('data-attr');
    if (action === 'rename-attr') renameAttribute(attrId);
    if (action === 'delete-attr') deleteAttribute(attrId);
  });

  wireDuplicateWarning(
    'gov-new-name',
    'gov-new-name-dupe',
    () => people,
    (p) => p.name,
    (match) =>
      `⚠ A person named “${match.name}” already exists. They have the role of “${match.role || 'not recorded'}”. ${describeMatchGroups(match)}`
  );
  wireDuplicateWarning(
    'gov-new-group-label',
    'gov-new-group-label-dupe',
    () => Object.values(groupMeta),
    (g) => g.label,
    (match) => `⚠ A group named “${match.label}” already exists.`
  );
  wireDuplicateWarning(
    'gov-new-attr-label',
    'gov-new-attr-label-dupe',
    () => Object.values(attributeMeta),
    (a) => a.label,
    (match) => `⚠ A person type named “${match.label}” already exists.`
  );

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

  // A4 portrait, matches the jsPDF options below.
  const PDF_PAGE_W_MM = 210;
  const PDF_PAGE_H_MM = 297;
  const PDF_MARGIN_MM = { top: 12, right: 12, bottom: 14, left: 12 };
  const PDF_CONTENT_W_MM = PDF_PAGE_W_MM - PDF_MARGIN_MM.left - PDF_MARGIN_MM.right;
  const PDF_CONTENT_H_MM = PDF_PAGE_H_MM - PDF_MARGIN_MM.top - PDF_MARGIN_MM.bottom;

  // Capture options for a section rendered ALONE inside #gov-pdf-root (see
  // captureIsolatedSection below). Explicit x/y/width/height pin the crop to
  // exactly the rendered wrapper — relying on html2canvas's default crop (the
  // element's live bounding rect) broke once a section sat partway down a
  // shared multi-section document: capture is isolation-based instead.
  const html2canvasOptsForWrapper = (wrapper) => ({
    scale: 1.5,
    useCORS: true,
    logging: false,
    backgroundColor: '#ffffff',
    scrollX: 0,
    scrollY: 0,
    x: 0,
    y: 0,
    width: wrapper.scrollWidth,
    height: wrapper.scrollHeight,
    windowWidth: wrapper.scrollWidth || 720,
    windowHeight: wrapper.scrollHeight || 200,
    onclone: (clonedDoc) => {
      const clonedRoot = clonedDoc.getElementById('gov-pdf-root');
      if (clonedRoot) {
        // Keep the export root at the viewport origin in the clone — html2canvas
        // with scrollX/Y:0 clips the left edge when the live node sits inside the
        // sidebar-offset .gov-layout-main column.
        clonedRoot.style.position = 'fixed';
        clonedRoot.style.left = '0';
        clonedRoot.style.top = '0';
        clonedRoot.style.right = 'auto';
        clonedRoot.style.bottom = 'auto';
        clonedRoot.style.opacity = '1';
        clonedRoot.style.zIndex = '2147483646';
        clonedRoot.style.pointerEvents = 'none';
        clonedRoot.removeAttribute('hidden');
      }
    },
  });

  // Renders just one section's markup into #gov-pdf-root (replacing whatever
  // was there) and waits a frame for layout, so every capture starts from a
  // clean (0,0) origin instead of an offset partway down a shared document.
  const isolateSectionInRoot = async (root, sectionOuterHtml) => {
    root.innerHTML = `<div class="gov-pdf-doc">${sectionOuterHtml}</div>`;
    root.hidden = false;
    await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
    return root.querySelector('.gov-pdf-doc');
  };

  const captureSectionCanvas = async (root, sectionOuterHtml) => {
    const wrapper = await isolateSectionInRoot(root, sectionOuterHtml);
    return html2pdf()
      .set({ html2canvas: html2canvasOptsForWrapper(wrapper) })
      .from(wrapper)
      .toCanvas()
      .get('canvas');
  };

  // Don't start a section's content within less than this much room at the
  // bottom of a page — otherwise its heading can end up stranded alone with
  // the table starting on the next page.
  const MIN_SECTION_START_MM = 30;

  // Places a canvas starting at cursorYmm on the CURRENT page, continuing
  // straight on from whatever's already there (cover, or a previous group) —
  // only breaking to a fresh page when there isn't enough room left, or the
  // canvas is taller than one page and needs to keep spilling onto more of
  // them. Returns the Y (mm) immediately below the content just placed, i.e.
  // the cursor for whatever gets placed next.
  const placeCanvasFlowing = (pdf, canvas, cursorYmm) => {
    const pxPerMm = canvas.width / PDF_CONTENT_W_MM;
    const fullPageHeightPx = Math.max(1, Math.floor(PDF_CONTENT_H_MM * pxPerMm));

    let y = cursorYmm;
    let availableMm = PDF_PAGE_H_MM - PDF_MARGIN_MM.bottom - y;
    if (availableMm < MIN_SECTION_START_MM) {
      pdf.addPage();
      y = PDF_MARGIN_MM.top;
      availableMm = PDF_CONTENT_H_MM;
    }

    let sliceY = 0;
    let sliceIndex = 0;
    let lastImgHmm = 0;
    let lastPlaceYmm = y;
    while (sliceY < canvas.height) {
      const budgetPx = sliceIndex === 0 ? Math.max(1, Math.floor(availableMm * pxPerMm)) : fullPageHeightPx;
      const sliceHeightPx = Math.min(budgetPx, canvas.height - sliceY);
      const sliceCanvas = document.createElement('canvas');
      sliceCanvas.width = canvas.width;
      sliceCanvas.height = sliceHeightPx;
      sliceCanvas
        .getContext('2d')
        .drawImage(canvas, 0, sliceY, canvas.width, sliceHeightPx, 0, 0, canvas.width, sliceHeightPx);
      const imgHmm = sliceHeightPx / pxPerMm;
      const placeYmm = sliceIndex === 0 ? y : PDF_MARGIN_MM.top;
      if (sliceIndex > 0) pdf.addPage();
      pdf.addImage(
        sliceCanvas.toDataURL('image/jpeg', 0.95),
        'JPEG',
        PDF_MARGIN_MM.left,
        placeYmm,
        PDF_CONTENT_W_MM,
        imgHmm
      );
      lastImgHmm = imgHmm;
      lastPlaceYmm = placeYmm;
      sliceY += sliceHeightPx;
      sliceIndex += 1;
    }
    return lastPlaceYmm + lastImgHmm;
  };

  // jsPDF itself isn't exposed globally by the bundle (it's a webpack-internal
  // module reference) — the only supported way to get an instance is through
  // html2pdf's own Worker chain. Bootstrapping through .toPdf() on the cover's
  // already-captured canvas both gets us that instance AND places page 1,
  // using html2pdf's own per-canvas placement math — which for a single short
  // canvas (no internal page break needed) is the exact same margin/width
  // formula placeCanvasFlowing uses, so the two stay in sync.
  const bootstrapPdfWithCanvas = (canvas, opt) => html2pdf().set(opt).from(canvas).toPdf().get('pdf');

  const runPdfExport = async (html, filename) => {
    const root = document.getElementById('gov-pdf-root');
    if (!root) throw new Error('PDF root missing');
    if (typeof html2pdf === 'undefined') {
      throw new Error('PDF library did not load — check your network, then try again');
    }

    const scrollX = window.scrollX || window.pageXOffset || 0;
    const scrollY = window.scrollY || window.pageYOffset || 0;
    // html2canvas blanks the top of pages when the window is scrolled; reset first.
    window.scrollTo(0, 0);

    // Park the capture root on <body> at the viewport origin. While it lives inside
    // .gov-layout-main (sidebar column), absolute left:0 is still offset in the
    // viewport and html2canvas (scrollX/Y:0) slices off the left of every page.
    const pdfRootHome = root.parentElement;
    const pdfRootNext = root.nextSibling;
    document.body.appendChild(root);
    root.style.position = 'fixed';
    root.style.left = '0';
    root.style.top = '0';
    root.style.right = 'auto';
    root.style.bottom = 'auto';
    root.style.zIndex = '2147483646';
    root.style.opacity = '1';
    root.style.pointerEvents = 'none';

    const restorePdfRoot = () => {
      root.hidden = true;
      root.innerHTML = '';
      root.style.position = '';
      root.style.left = '';
      root.style.top = '';
      root.style.right = '';
      root.style.bottom = '';
      root.style.zIndex = '';
      root.style.opacity = '';
      root.style.pointerEvents = '';
      if (pdfRootHome) {
        pdfRootHome.insertBefore(root, pdfRootNext);
      }
      window.scrollTo(scrollX, scrollY);
    };

    try {
      // Parse the document off-DOM to split it into independent section markup
      // strings. Each section is then rendered into #gov-pdf-root ALONE (one at a
      // time, replacing the previous section) and captured from that clean slate —
      // capturing sections in place inside one shared, all-sections-at-once
      // document made every capture's crop rect depend on that section's offset
      // partway down the page, which is what clipped content sideways.
      const parser = document.createElement('div');
      parser.innerHTML = html;
      const doc = parser.querySelector('.gov-pdf-doc');
      if (!doc) throw new Error('PDF document could not be built');
      const cover = doc.querySelector('.gov-pdf-cover');
      const groups = Array.from(doc.querySelectorAll('.gov-pdf-group'));
      const sections = [cover, ...groups].filter(Boolean);
      if (!sections.length) throw new Error('PDF document could not be built');

      const [firstSection, ...restSections] = sections;
      const coverCanvas = await captureSectionCanvas(root, firstSection.outerHTML);
      const pdf = await bootstrapPdfWithCanvas(coverCanvas, {
        margin: [PDF_MARGIN_MM.top, PDF_MARGIN_MM.right, PDF_MARGIN_MM.bottom, PDF_MARGIN_MM.left],
        image: { type: 'jpeg', quality: 0.95 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      });

      // Groups now flow straight on from the cover instead of each forcing a
      // fresh page — short groups no longer leave most of a page blank. Track
      // where the cover's own content (placed by html2pdf itself, above)
      // actually ended so the first group knows where it can continue from.
      const coverPxPerMm = coverCanvas.width / PDF_CONTENT_W_MM;
      let cursorYmm = PDF_MARGIN_MM.top + coverCanvas.height / coverPxPerMm;
      if (pdf.internal.getNumberOfPages() > 1) {
        // Cover spilled onto extra pages on its own (unexpectedly tall) —
        // treat as if the page is full so the next section starts fresh.
        cursorYmm = PDF_PAGE_H_MM;
      }

      for (const section of restSections) {
        const canvas = await captureSectionCanvas(root, section.outerHTML);
        cursorYmm = placeCanvasFlowing(pdf, canvas, cursorYmm);
      }

      // Footer as real text rather than another captured section — it's one
      // line, and vector text avoids yet another canvas/page-break edge case.
      const footerText = 'Point-in-time working list from the membership tool.';
      const footerNeededMm = 10;
      let footerY = cursorYmm + 6;
      if (footerY + footerNeededMm > PDF_PAGE_H_MM - PDF_MARGIN_MM.bottom) {
        pdf.addPage();
        footerY = PDF_MARGIN_MM.top;
      }
      pdf.setDrawColor(209, 213, 219);
      pdf.line(PDF_MARGIN_MM.left, footerY, PDF_PAGE_W_MM - PDF_MARGIN_MM.right, footerY);
      pdf.setFontSize(8);
      pdf.setTextColor(107, 114, 128);
      pdf.text(footerText, PDF_MARGIN_MM.left, footerY + 4);

      pdf.save(filename);
    } finally {
      restorePdfRoot();
    }
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

  const setHistoryActionStatus = (message, isError = false) => {
    const el = document.getElementById('gov-history-reseed-status');
    if (el) {
      el.hidden = !message;
      el.textContent = message || '';
      el.classList.toggle('is-error', !!isError);
      if (message) {
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }
    if (message) setStatus(message, isError);
  };

  const reseedUnassignedFromSeed = () => {
    const btn = document.getElementById('gov-history-reseed');
    if (!canConfirmMembership) {
      const msg =
        'Only a membership confirmer (admin) can rebuild lists from the seed file. Sign in as Lisa or William.';
      setHistoryActionStatus(msg, true);
      window.alert(msg);
      return;
    }
    const ok = window.confirm(
      'Rebuild membership from the seed people file?\n\n' +
        'This resets Board / Steering / Technical (and other seed placements) to the original list.\n' +
        'People who are only in the seed as unassigned stay in the pool.\n' +
        'Custom people not in the seed file keep their places.\n\n' +
        'Continue?'
    );
    if (!ok) {
      setHistoryActionStatus('Rebuild cancelled.');
      return;
    }
    setHistoryActionStatus('Rebuilding lists from seed file…');
    if (btn) btn.disabled = true;
    postJson({ action: 'reseed_unassigned' })
      .then((data) => {
        if (data.state) applyBundle(data.state);
        dirty = false;
        const r = data.reseeded || {};
        const inserted = r.insertedPairs ?? 0;
        const people = r.restoredPeople ?? 0;
        const total = r.membershipTotal ?? 0;
        const seedPairs = r.seedPairs ?? 0;
        const msg =
          inserted > 0
            ? `Rebuilt from seed: ${people} people, ${inserted} placements (seed had ${seedPairs}). Membership rows now: ${total}. Check Board (8) / Steering (17) / Technical (18) above.`
            : `Rebuild reported 0 placements. Seed pairs available: ${seedPairs}. Groups: ${(r.allowedGroups || []).join(', ') || 'none'}.`;
        setHistoryActionStatus(msg, inserted < 1);
        window.alert(msg);
        render();
        refreshHistoryQuiet();
        // Jump to Project Board so the result is visible
        const board = document.getElementById('project-board');
        if (board) board.scrollIntoView({ behavior: 'smooth', block: 'start' });
      })
      .catch((err) => {
        const msg = err.message || 'Could not rebuild lists from seed';
        setHistoryActionStatus(msg, true);
        window.alert(msg);
      })
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
    // Keep clickable so we can explain permissions instead of a dead control.
    if (reseedBtn) {
      reseedBtn.disabled = false;
      reseedBtn.title = canConfirmMembership
        ? 'Rebuild Board / Steering / Technical from the seed people file'
        : 'Sign in as a membership confirmer to rebuild lists from seed';
    }
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

  /** Scroll a sidebar target inside the sticky aside — do not move the main column. */
  const scrollSidebarTarget = (target) => {
    const sidebar = document.querySelector('.gov-sidebar');
    if (!sidebar || !target || !sidebar.contains(target)) return false;
    const overflow = getComputedStyle(sidebar).overflowY;
    const canScrollAside =
      (overflow === 'auto' || overflow === 'scroll') &&
      sidebar.scrollHeight > sidebar.clientHeight + 4;
    if (canScrollAside) {
      const delta = target.getBoundingClientRect().top - sidebar.getBoundingClientRect().top;
      sidebar.scrollTo({ top: Math.max(0, sidebar.scrollTop + delta - 8), behavior: 'smooth' });
    } else {
      // Stacked layout (narrow screens): page scroll is expected.
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    return true;
  };

  document.querySelector('.gov-sidebar-jump')?.addEventListener('click', (e) => {
    const link = e.target.closest('a.gov-sidebar-jump-btn[href^="#"]');
    if (!link) return;
    const id = link.getAttribute('href').slice(1);
    if (!id) return;
    const target = document.getElementById(id);
    if (!target) return;
    // Sidebar sections are collapsed <details> — open the one being jumped to.
    if (target.tagName === 'DETAILS') target.open = true;
    if (scrollSidebarTarget(target)) {
      e.preventDefault();
      if (history.replaceState) history.replaceState(null, '', `#${id}`);
      else location.hash = id;
    }
  });

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
      if (!location.hash) return;
      const el = document.querySelector(location.hash);
      if (!el) return;
      if (!scrollSidebarTarget(el)) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    })
    .catch((err) => {
      setStatus(err.message || 'Could not load membership data', true);
      groupsEl.innerHTML = `<p class="gov-empty">${esc(err.message || 'No membership data loaded.')}</p>`;
    });
})();
