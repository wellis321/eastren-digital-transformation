(() => {
  'use strict';

  const THEME_LABELS = {
    hms_change: 'HMS / systems',
    workforce: 'Workforce / scheduling',
    tenant_channel: 'Tenant channel',
    integration: 'Integration / API',
    repairs_contract: 'Repairs contract',
    programme_grant: 'Programme grant',
    other: 'Other',
  };

  const esc = (s) => {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  };

  const formatGbp = (amount, vat) => {
    const n = Number(amount);
    if (!Number.isFinite(n)) return '—';
    const base = `£${n.toLocaleString('en-GB', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
    if (vat === 'excluded') return `${base} ex VAT`;
    if (vat === 'included') return `${base} inc VAT`;
    return base;
  };

  const groupRowsByAuthority = (rows, key) => {
    const map = new Map();
    rows.forEach((row) => {
      const name = row[key] || row.council || row.organisation || '—';
      if (!map.has(name)) map.set(name, []);
      map.get(name).push(row);
    });
    return [...map.entries()]
      .sort((a, b) => a[0].localeCompare(b[0], 'en-GB'))
      .map(([name, groupRows]) => ({
        name,
        rows: groupRows.sort(
          (a, b) => (Number(b.amountGbp) || 0) - (Number(a.amountGbp) || 0)
        ),
      }));
  };

  const renderContractRow = (row, { includeAuthority = true, authorityCell = '' } = {}) => {
    const theme = THEME_LABELS[row.theme] || row.theme || '—';
    const note = row.text || row.note;
    const source = row.href
      ? `<a href="${esc(row.href)}" target="_blank" rel="noopener noreferrer">Source</a>`
      : '—';
    const authority = includeAuthority
      ? `<td class="benchmarks-authority"><strong>${esc(row.council || row.organisation)}</strong></td>`
      : authorityCell;
    return `<tr>
      ${authority}
      <td><span class="badge benchmarks-theme">${esc(theme)}</span></td>
      <td><strong>${esc(row.label)}</strong>${note ? `<br><span class="benchmarks-note">${esc(note)}</span>` : ''}</td>
      <td class="benchmarks-amount">${esc(formatGbp(row.amountGbp, row.vat))}</td>
      <td>${esc(row.period || '—')}</td>
      <td>${source}</td>
    </tr>`;
  };

  const renderTable = (mount, rows, { groupBy = null, authorityLabel = 'Council / body' } = {}) => {
    if (!mount) return;
    if (!rows.length) {
      mount.innerHTML = '<p style="margin:0;color:#555;">No rows match this filter.</p>';
      return;
    }

    let body;
    if (groupBy) {
      body = groupRowsByAuthority(rows, groupBy)
        .map((group) =>
          group.rows
            .map((row, index) => {
              const authorityCell =
                index === 0
                  ? `<td rowspan="${group.rows.length}" class="benchmarks-authority"><strong>${esc(group.name)}</strong><br><span class="benchmarks-note">${group.rows.length} contract${group.rows.length === 1 ? '' : 's'}</span></td>`
                  : '';
              return renderContractRow(row, { includeAuthority: false, authorityCell });
            })
            .join('')
        )
        .join('');
    } else {
      body = rows.map((row) => renderContractRow(row)).join('');
    }

    mount.innerHTML = `<div class="table-wrap"><table class="benchmarks-table">
      <thead><tr>
        <th scope="col">${esc(authorityLabel)}</th>
        <th scope="col">Theme</th>
        <th scope="col">What it is</th>
        <th scope="col">Published figure</th>
        <th scope="col">Period</th>
        <th scope="col">Link</th>
      </tr></thead>
      <tbody>${body}</tbody>
    </table></div>`;
  };

  let scotlandRows = [];
  let englandRows = [];

  const applyFilter = () => {
    const theme = document.getElementById('benchmarks-theme-filter')?.value || 'all';
    const filter = (rows) => (theme === 'all' ? rows : rows.filter((r) => r.theme === theme));
    renderTable(document.getElementById('benchmarks-scotland-mount'), filter(scotlandRows), {
      groupBy: 'council',
      authorityLabel: 'Local authority',
    });
    renderTable(document.getElementById('benchmarks-england-mount'), filter(englandRows), {
      groupBy: 'organisation',
      authorityLabel: 'Organisation',
    });
    const countEl = document.getElementById('benchmarks-count');
    if (countEl) {
      const n = filter(scotlandRows).length + filter(englandRows).length;
      countEl.textContent = `${n} row${n === 1 ? '' : 's'} shown`;
    }
  };

  const init = async () => {
    document.getElementById('benchmarks-theme-filter')?.addEventListener('change', applyFilter);

    try {
      const [scotRes, engRes] = await Promise.all([
        fetch('assets/data/peer-procurement-scotland.json'),
        fetch('assets/data/peer-benchmarks-england.json'),
      ]);
      const scotland = scotRes.ok ? await scotRes.json() : { rows: [] };
      const england = engRes.ok ? await engRes.json() : { rows: [], signals: [] };

      scotlandRows = scotland.rows || [];
      englandRows = england.rows || [];

      const hint = document.getElementById('benchmarks-scotland-hint');
      if (hint) {
        const councils = new Set(scotlandRows.map((r) => r.council).filter(Boolean)).size;
        hint.textContent = `${scotlandRows.length} published contract${scotlandRows.length === 1 ? '' : 's'} across ${councils} local authorit${councils === 1 ? 'y' : 'ies'} from Public Contracts Scotland — I add more as I find them.`;
      }

      const signalsMount = document.getElementById('benchmarks-signals-mount');
      if (signalsMount && england.signals?.length) {
        signalsMount.innerHTML = `<ul class="benchmarks-signals">${england.signals
          .map(
            (s) =>
              `<li><strong>${esc(s.organisation)}</strong> — ${esc(s.text)} <a href="${esc(s.href)}" target="_blank" rel="noopener noreferrer">More</a></li>`
          )
          .join('')}</ul>`;
      }

      applyFilter();
    } catch {
      const mount = document.getElementById('benchmarks-scotland-mount');
      if (mount) mount.innerHTML = '<p style="margin:0;color:#555;">Could not load data — use <code>php -S localhost:8080</code> from the site root.</p>';
    }
  };

  if (document.getElementById('benchmarks-scotland-mount')) init();
})();
