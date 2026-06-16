(() => {
  'use strict';

  const mount = document.getElementById('tenant-channels-mount');
  if (!mount) return;

  const DATA_URL = 'assets/data/tenant-digital-channels.json';

  const esc = (s) => {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  };

  fetch(DATA_URL)
    .then((r) => {
      if (!r.ok) throw new Error(String(r.status));
      return r.json();
    })
    .then((data) => {
      const meta = data.meta || {};
      const councils = Array.isArray(data.councils) ? data.councils : [];

      const table = document.createElement('table');
      table.className = 'tenant-channels-table';
      table.innerHTML = `
        <thead>
          <tr>
            <th scope="col">Council</th>
            <th scope="col">In this table</th>
            <th scope="col">Primary public link(s)</th>
          </tr>
        </thead>
        <tbody></tbody>
      `;
      const tbody = table.querySelector('tbody');

      councils.forEach((c) => {
        const name = c.name || c.slug || '—';
        const status = c.status === 'linked' ? 'linked' : 'unverified';
        const urls = Array.isArray(c.urls) ? c.urls : [];

        const tr = document.createElement('tr');

        const tdName = document.createElement('td');
        tdName.innerHTML = `<strong>${esc(name)}</strong>`;

        const tdStatus = document.createElement('td');
        if (status === 'linked') {
          tdStatus.innerHTML = '<span class="badge badge-green">Linked</span>';
        } else {
          tdStatus.innerHTML = '<span class="badge">Not linked yet</span>';
        }

        const tdLinks = document.createElement('td');
        if (urls.length) {
          const ul = document.createElement('ul');
          ul.className = 'tenant-channel-links';
          urls.forEach((u) => {
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = u.href;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.textContent = u.label || u.href;
            li.appendChild(a);
            ul.appendChild(li);
          });
          tdLinks.appendChild(ul);
        } else {
          tdLinks.innerHTML = `<span style="color:#555;font-size:0.92rem;">${esc(
            'No URL stored yet — check the council housing/repairs pages.'
          )}</span>`;
        }

        tr.appendChild(tdName);
        tr.appendChild(tdStatus);
        tr.appendChild(tdLinks);
        tbody.appendChild(tr);
      });

      const wrap = document.createElement('div');
      wrap.className = 'table-wrap';
      wrap.appendChild(table);
      mount.appendChild(wrap);

      if (meta.lastReviewed) {
        const foot = document.createElement('p');
        foot.style.margin = '0.75rem 0 0';
        foot.style.color = '#555';
        foot.style.fontSize = '0.88rem';
        foot.textContent = `Table data last reviewed: ${meta.lastReviewed}.`;
        mount.appendChild(foot);
      }
    })
    .catch(() => {
      mount.innerHTML =
        '<p style="margin:0;color:#555;">Could not load the channel list. Serve the site over HTTP so <code>assets/data/tenant-digital-channels.json</code> can load.</p>';
    });
})();
