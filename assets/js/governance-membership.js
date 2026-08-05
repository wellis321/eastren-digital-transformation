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
  let tallies = {};
  let needsAssessment = [];
  let canEdit = true;
  let canVote = true;
  let canShape = true;
  let currentUser = { id: 0, label: '' };
  let dbInitialized = false;
  let saveTimer = null;
  let dirty = false;

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

  const getTally = (personId, groupId) => {
    const t = tallies?.[personId]?.[groupId];
    if (!t || typeof t !== 'object') {
      return { yes: 0, maybe: 0, no: 0, total: 0, voters: [] };
    }
    return {
      yes: Number(t.yes) || 0,
      maybe: Number(t.maybe) || 0,
      no: Number(t.no) || 0,
      total: Number(t.total) || 0,
      voters: Array.isArray(t.voters) ? t.voters : [],
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
    tallies = asPlainObject(bundle.tallies);
    needsAssessment = Array.isArray(bundle.needsAssessment) ? bundle.needsAssessment : [];
    canEdit = bundle.canEdit !== false;
    canVote = bundle.canVote !== false;
    canShape = bundle.canShape !== false;
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
    if (vote === 'unset') {
      delete myVotes[personId][groupId];
      if (!Object.keys(myVotes[personId]).length) delete myVotes[personId];
    } else {
      myVotes[personId][groupId] = vote;
    }

    // Optimistic tally adjust for current user only
    if (!tallies[personId]) tallies[personId] = {};
    if (!tallies[personId][groupId]) {
      tallies[personId][groupId] = { yes: 0, maybe: 0, no: 0, total: 0, voters: [] };
    }
    const t = tallies[personId][groupId];
    t.voters = Array.isArray(t.voters) ? t.voters.slice() : [];
    const label = currentUser.label || 'You';
    const uid = currentUser.id;
    if (prev !== 'unset') {
      t[prev] = Math.max(0, (Number(t[prev]) || 0) - 1);
      t.total = Math.max(0, (Number(t.total) || 0) - 1);
      t.voters = t.voters.filter((v) => v.userId !== uid);
    }
    if (vote !== 'unset') {
      t[vote] = (Number(t[vote]) || 0) + 1;
      t.total = (Number(t.total) || 0) + 1;
      t.voters.push({ userId: uid, name: label, vote });
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

  const placePerson = (personId, toGroupId, mode, fromGroupId) => {
    if (!canShape) {
      setStatus('Sign in to move people between groups', true);
      return;
    }
    if (!toGroupId) {
      setStatus('Choose a target group first', true);
      return;
    }
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
      })
      .catch((err) => setStatus(err.message || 'Could not update group membership', true));
  };

  const addToGroup = (personId, groupId) => {
    placePerson(personId, groupId, 'add', null);
  };

  const removeFromGroup = (personId, groupId) => {
    if (!canShape) return;
    const person = personById(personId);
    if (!person) return;
    state.membership[personId] = groupsFor(person).filter((g) => g !== groupId);
    dbInitialized = true;
    if (Array.isArray(state.order[groupId])) {
      state.order[groupId] = state.order[groupId].filter((id) => id !== personId);
    }
    if (myVotes[personId]) {
      delete myVotes[personId][groupId];
      if (!Object.keys(myVotes[personId]).length) delete myVotes[personId];
    }
    if (tallies[personId]) {
      delete tallies[personId][groupId];
    }
    needsAssessment = needsAssessment.filter(
      (n) => !(n.personId === personId && n.groupId === groupId)
    );
    render();
    persistSoon();
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
      })
      .catch((err) => setStatus(err.message || 'Could not create group', true));
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

  const renderPlaceControls = (person, groupId) => {
    if (!canShape) return '';
    const opts = otherGroupOptions(person.id, groupId);
    if (!opts) {
      return `<details class="gov-place-details"><summary>Place on another group</summary><p class="gov-place-note">Already on every group</p></details>`;
    }
    return `<details class="gov-place-details">
      <summary>Place on another group</summary>
      <div class="gov-place">
        <label class="visually-hidden" for="place-${esc(person.id)}-${esc(groupId)}">Place ${esc(person.name)} on another group</label>
        <select id="place-${esc(person.id)}-${esc(groupId)}" data-place-select="${esc(person.id)}" data-from-group="${esc(groupId)}">
          <option value="">Choose group…</option>
          ${opts}
        </select>
        <button type="button" class="gov-btn gov-btn--muted" data-action="also-on" data-id="${esc(person.id)}" data-group="${esc(groupId)}" title="Keep on this list and also add to the chosen group">Also on</button>
        <button type="button" class="gov-btn gov-btn--muted" data-action="move-to" data-id="${esc(person.id)}" data-group="${esc(groupId)}" title="Remove from this list and put on the chosen group">Move to</button>
      </div>
    </details>`;
  };

  const renderTallyCompact = (personId, groupId) => {
    const t = getTally(personId, groupId);
    if (!t.total) {
      return `<span class="gov-tally-compact gov-tally-compact--empty" title="No opinions yet">—</span>`;
    }
    const byVote = { yes: [], maybe: [], no: [] };
    t.voters.forEach((v) => {
      if (byVote[v.vote]) byVote[v.vote].push(v.name || 'Someone');
    });
    const tip = [
      byVote.yes.length ? `Yes: ${byVote.yes.join(', ')}` : '',
      byVote.maybe.length ? `Maybe: ${byVote.maybe.join(', ')}` : '',
      byVote.no.length ? `No: ${byVote.no.join(', ')}` : '',
    ]
      .filter(Boolean)
      .join(' · ');
    return `<span class="gov-tally-compact" title="${esc(tip)}">
      <span class="gov-tally-compact__yes">${t.yes}Y</span>
      <span class="gov-tally-compact__maybe">${t.maybe}~</span>
      <span class="gov-tally-compact__no">${t.no}N</span>
    </span>`;
  };

  const renderRow = (person, groupId) => {
    const vote = getMyVote(person.id, groupId);
    const voteDisabled = canVote ? '' : ' disabled';
    const awaiting = needsKey(person.id, groupId)
      ? '<span class="gov-mark-needed">Needs you</span>'
      : '';
    const otherLists = groupsFor(person)
      .filter((g) => g !== groupId)
      .map((g) => groupMeta[g]?.label || g);
    const alsoOn =
      otherLists.length > 0
        ? `<span class="gov-also-on">Also: ${esc(otherLists.join(', '))}</span>`
        : '';
    const removeBtn = canShape
      ? `<button type="button" class="gov-icon-btn gov-icon-btn--danger" data-action="remove" data-id="${esc(person.id)}" data-group="${esc(groupId)}" title="Remove from this group" aria-label="Remove from group">×</button>`
      : '';
    const rowId = `member-${esc(groupId)}-${esc(person.id)}`;
    return `<li class="gov-person-row" id="${rowId}" data-id="${esc(person.id)}" data-vote="${esc(vote)}">
      <div class="gov-person-main">
        <div class="gov-person-icons" aria-hidden="true">${voteIcon(vote)}</div>
        <div class="gov-person-text">
          <div class="gov-person-line">
            <strong class="gov-person-name">${esc(person.name)}</strong>
            ${awaiting}
            ${renderTallyCompact(person.id, groupId)}
          </div>
          <span class="gov-person-role">${esc(person.role || 'Role not recorded')}</span>
          ${alsoOn}
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
        ${removeBtn}
      </div>
    </li>`;
  };

  const renderRoster = (groupId, peopleList) => {
    if (!peopleList.length) return '';
    return `<ul class="gov-roster" aria-label="Members at a glance">
      ${peopleList
        .map((p) => {
          const vote = getMyVote(p.id, groupId);
          const pending = needsKey(p.id, groupId) ? ' gov-roster-chip--pending' : '';
          const voteClass =
            vote === 'yes'
              ? ' gov-roster-chip--yes'
              : vote === 'maybe'
                ? ' gov-roster-chip--maybe'
                : vote === 'no'
                  ? ' gov-roster-chip--no'
                  : '';
          return `<li><a class="gov-roster-chip${voteClass}${pending}" href="#member-${esc(groupId)}-${esc(p.id)}">${esc(p.name)}</a></li>`;
        })
        .join('')}
    </ul>`;
  };

  const renderGroup = (groupId) => {
    const meta = groupMeta[groupId] || { label: groupId, blurb: '', isCore: true };
    ensureOrder(groupId);
    const ids = state.order[groupId] || sortedIdsForGroup(groupId);
    state.order[groupId] = ids;
    const members = ids.map((id) => personById(id)).filter(Boolean);
    const rows = members.map((p) => renderRow(p, groupId)).join('');
    const count = members.length;
    const countLabel = count === 1 ? '1 person' : `${count} people`;
    const empty = !ids.length
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
    const toolsHtml = tools.length ? `<div class="gov-group-tools">${tools.join('')}</div>` : '';
    const customClass = meta.isCore === false ? ' gov-group-panel--custom' : '';
    const customBadge =
      meta.isCore === false ? '<span class="gov-group-badge">Custom</span>' : '';

    return `<section class="gov-group-panel${customClass}" id="${esc(groupId)}">
      <header class="gov-group-head">
        <div class="gov-group-head-text">
          <p class="gov-group-kicker">${esc(countLabel)}${customBadge}</p>
          <h2 class="gov-group-title">${esc(meta.label || groupId)}</h2>
          <p class="gov-group-blurb">${esc(meta.blurb || '')}</p>
        </div>
        ${toolsHtml}
      </header>
      ${renderRoster(groupId, members)}
      ${empty}
      ${members.length ? `<h3 class="gov-assess-heading">Assess &amp; place</h3><ul class="gov-person-list">${rows}</ul>` : ''}
    </section>`;
  };

  const renderAwaiting = () => {
    if (!awaitingEl) return;
    if (!needsAssessment.length) {
      awaitingEl.innerHTML = `<p class="gov-empty">You have no new people left to assess.</p>`;
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
        return `<a href="#${esc(g)}">${esc(groupMeta[g]?.label || g)} <span class="gov-jump-count">${n}</span></a>`;
      }),
      `<a href="#pool">Unassigned <span class="gov-jump-count">${poolCount}</span></a>`,
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
        const add = canShape
          ? `<div class="gov-pool-add">
            <label class="visually-hidden" for="add-${esc(p.id)}">Add ${esc(p.name)} to group</label>
            <select id="add-${esc(p.id)}" data-add-select="${esc(p.id)}">${groupOpts}</select>
            <button type="button" class="gov-btn" data-action="add" data-id="${esc(p.id)}">Add</button>
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
    if (action === 'also-on' || action === 'move-to') {
      const select = groupsEl.querySelector(
        `select[data-place-select="${CSS.escape(id)}"][data-from-group="${CSS.escape(group)}"]`
      );
      const toGroupId = select?.value || '';
      placePerson(id, toGroupId, action === 'move-to' ? 'move' : 'add', group);
    }
  });

  document.getElementById('gov-pool-body')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="add"]');
    if (!btn || !canShape) return;
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
          voters: t.voters
            .map((v) => `${v.name} (${v.vote})`)
            .join('; '),
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
      'Who marked',
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
          r.voters,
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

  const buildPdfHtml = () => {
    const asOf = formatAsOfDate();
    const sections = groupOrder
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

    return `<div class="gov-pdf-doc">
      <header class="gov-pdf-cover">
        <p class="gov-pdf-eyebrow">East Renfrewshire Council — Housing Digital Transformation</p>
        <h1>Governance membership</h1>
        <p class="gov-pdf-meta">${esc(asOf)}</p>
      </header>
      ${sections}
      <footer class="gov-pdf-foot">Working list only — final membership sits with the Project Manager / Project Owner.</footer>
    </div>`;
  };

  const exportPdf = () => {
    const root = document.getElementById('gov-pdf-root');
    const btn = document.getElementById('gov-export-pdf');
    if (!root) return;
    if (typeof html2pdf === 'undefined') {
      setStatus('PDF library did not load — check your network, then try again', true);
      return;
    }
    root.innerHTML = buildPdfHtml();
    root.hidden = false;
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Generating PDF…';
    }
    setStatus('Generating PDF…');
    const opt = {
      margin: [12, 12, 14, 12],
      filename: `governance-membership-${exportStamp()}.pdf`,
      image: { type: 'jpeg', quality: 0.95 },
      html2canvas: { scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff' },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      pagebreak: { mode: ['css', 'legacy'], avoid: ['.gov-pdf-group-head', 'tr'] },
    };
    html2pdf()
      .set(opt)
      .from(root.querySelector('.gov-pdf-doc'))
      .save()
      .then(() => {
        root.hidden = true;
        root.innerHTML = '';
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Export PDF';
        }
        setStatus('PDF exported');
      })
      .catch((err) => {
        console.error(err);
        root.hidden = true;
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Export PDF';
        }
        setStatus('PDF export failed — try again', true);
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
