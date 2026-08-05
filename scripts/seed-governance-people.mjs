#!/usr/bin/env node
/**
 * Seeds assets/data/governance-people.json from documents/people/lists/Re-Building of People.csv
 * Names and roles only — no emails. CSV path is gitignored; script exits quietly if missing.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const csvPath = path.join(root, 'documents/people/lists/Re-Building of People.csv');
const outPath = path.join(root, 'assets/data/governance-people.json');

const GROUP_MAP = {
  'Project Board': 'project-board',
  'Steering Group': 'steering-group',
  'Technical Group': 'technical-group',
  'Business Group': 'business-group',
};

if (!fs.existsSync(csvPath)) {
  console.warn('CSV not found (gitignored folder may be missing):', csvPath);
  process.exit(0);
}

const parseCsv = (text) => {
  const lines = text.replace(/^\uFEFF/, '').split(/\r?\n/).filter((l) => l.length);
  const headers = lines[0].split(',').map((h) => h.trim());
  return lines.slice(1).map((line) => {
    const cells = [];
    let cur = '';
    let inQ = false;
    for (let i = 0; i < line.length; i++) {
      const ch = line[i];
      if (ch === '"') { inQ = !inQ; continue; }
      if (ch === ',' && !inQ) { cells.push(cur); cur = ''; continue; }
      cur += ch;
    }
    cells.push(cur);
    const row = {};
    headers.forEach((h, i) => { row[h] = (cells[i] || '').trim(); });
    return row;
  });
};

const cleanName = (raw) => {
  let s = String(raw || '').trim();
  if (s.includes(',')) {
    const [last, first] = s.split(',').map((x) => x.trim());
    s = `${first.replace(/\d+$/, '').trim()} ${last}`.trim();
  } else {
    s = s.split(/\s+/).map((p) => p.replace(/(\D+)\d+$/, '$1')).join(' ');
  }
  return s;
};

const slugify = (name) => name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

const rows = parseCsv(fs.readFileSync(csvPath, 'utf8'));
const seen = new Map();
const people = [];
for (const row of rows) {
  const name = cleanName(row.Person || '');
  if (!name) continue;
  const role = (row.Role || '').trim();
  const rawGroups = (row.Groups || '').trim();
  const groups = [];
  if (rawGroups && rawGroups !== '[]' && rawGroups !== '""') {
    for (const part of rawGroups.split(',')) {
      const g = part.trim().replace(/^\[|\]$/g, '').replace(/^"|"$/g, '');
      if (GROUP_MAP[g]) groups.push(GROUP_MAP[g]);
    }
  }
  const id = slugify(name);
  if (seen.has(id)) {
    const existing = seen.get(id);
    for (const g of groups) if (!existing.groups.includes(g)) existing.groups.push(g);
    if (role && !existing.role) existing.role = role;
    continue;
  }
  const person = { id, name, role, groups };
  seen.set(id, person);
  people.push(person);
}
people.sort((a, b) => a.name.localeCompare(b.name, 'en-GB'));

const data = {
  meta: {
    note: 'Names and roles only — no emails. Source CSVs under documents/people/lists/ (gitignored).',
    lastReviewed: '2026-06-16',
    groups: {
      'project-board': { label: 'Project Board', blurb: 'Senior movers and shakers who hold purse strings and can unlock time and decisions.', sort: 1 },
      'steering-group': { label: 'Steering Group', blurb: 'Service-wide knowledge across the parts of the operation — informs the Project Board.', sort: 2 },
      'technical-group': { label: 'Technical Group', blurb: 'IT, systems, data and related depth — called when the detail matters, without boring everyone else.', sort: 3 },
      'business-group': { label: 'Business Group', blurb: 'Expertise on tap (e.g. Social Care, planners). Contactable people for as-needed support over the year.', sort: 4 },
    },
  },
  people,
};
fs.writeFileSync(outPath, JSON.stringify(data, null, 2) + '\n');
console.log('Wrote', outPath, `(${people.length} people)`);
