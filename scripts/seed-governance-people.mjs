#!/usr/bin/env node
/**
 * Seeds assets/data/governance-people.json from documents/people/lists/
 * Names, roles, groups, and project attributes only — no emails.
 * Paths are gitignored; script exits quietly if the primary CSV is missing.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { spawnSync } from 'child_process';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const csvPath = path.join(root, 'documents/people/lists/Re-Building of People.csv');
const projectPeoplePath = path.join(root, 'documents/people/lists/Project People.csv');
const xlsxPath = path.join(
  root,
  'documents/people/lists/Repairs solution implementation Stakeholder responsibilities v0.1.xlsx'
);
const outPath = path.join(root, 'assets/data/governance-people.json');

const GROUP_MAP = {
  'Project Board': 'project-board',
  'Steering Group': 'steering-group',
  'Technical Group': 'technical-group',
  'Business Group': 'business-group',
};

const ATTRIBUTE_LABELS = {
  advisor: 'Advisor',
  designer: 'Designer',
  decider: 'Decider',
  champion: 'Champion',
  trainer: 'Trainer',
  tester: 'Tester',
  influencer: 'Influencer',
  customer: 'Customer',
  expert: 'Expert',
  informed: 'Informed',
  consulted: 'Consulted',
  'stakeholder-key': 'Key stakeholder',
  'stakeholder-primary': 'Primary stakeholder',
  'stakeholder-secondary': 'Secondary stakeholder',
};

if (!fs.existsSync(csvPath)) {
  console.warn('CSV not found (gitignored folder may be missing):', csvPath);
  process.exit(0);
}

const stripQuotes = (s) => {
  const t = String(s ?? '').trim();
  if (t.length >= 2 && t.startsWith('"') && t.endsWith('"')) {
    return t.slice(1, -1).replace(/""/g, '"').trim();
  }
  return t;
};

const parseCsvLine = (line) => {
  const cells = [];
  let cur = '';
  let inQ = false;
  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (ch === '"') {
      if (inQ && line[i + 1] === '"') {
        cur += '"';
        i++;
        continue;
      }
      inQ = !inQ;
      continue;
    }
    if (ch === ',' && !inQ) {
      cells.push(cur);
      cur = '';
      continue;
    }
    cur += ch;
  }
  cells.push(cur);
  return cells;
};

const parseCsv = (text) => {
  const lines = text
    .replace(/^\uFEFF/, '')
    .split(/\r?\n/)
    .filter((l) => l.trim().length);
  const headers = parseCsvLine(lines[0]).map((h) => stripQuotes(h));
  return lines.slice(1).map((line) => {
    const cells = parseCsvLine(line);
    const row = {};
    headers.forEach((h, i) => {
      row[h] = stripQuotes(cells[i] || '');
    });
    return row;
  });
};

const cleanName = (raw) => {
  let s = String(raw || '').trim();
  if (s.includes(',')) {
    const [last, first] = s.split(',').map((x) => x.trim());
    s = `${first.replace(/\d+$/, '').trim()} ${last}`.trim();
  } else {
    s = s
      .split(/\s+/)
      .map((p) => p.replace(/(\D+)\d+$/, '$1'))
      .join(' ');
  }
  return s.replace(/\s+/g, ' ').trim();
};

const slugify = (name) =>
  name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const uniq = (arr) => {
  const seen = new Set();
  const out = [];
  for (const x of arr) {
    if (!x || seen.has(x)) continue;
    seen.add(x);
    out.push(x);
  }
  return out;
};

/** Stakeholder category from Project People.csv → stakeholder-* tags */
const loadStakeholderCategories = () => {
  const bySlug = new Map();
  if (!fs.existsSync(projectPeoplePath)) return bySlug;
  for (const row of parseCsv(fs.readFileSync(projectPeoplePath, 'utf8'))) {
    const name = cleanName(row.Person || '');
    if (!name) continue;
    const cat = String(row['Stakeholder Category'] || '')
      .trim()
      .toLowerCase();
    if (cat === 'key' || cat === 'primary' || cat === 'secondary') {
      bySlug.set(slugify(name), `stakeholder-${cat}`);
    }
  }
  return bySlug;
};

/**
 * Responsibility columns (Advisor, Champion, Decider, …) from the stakeholder xlsx.
 * Uses Python + openpyxl when available; otherwise returns an empty map.
 */
const loadResponsibilityTags = () => {
  const bySlug = new Map();
  if (!fs.existsSync(xlsxPath)) return bySlug;
  const py = `
import json, re, sys
try:
    import openpyxl
except ImportError:
    print("{}")
    raise SystemExit(0)
path = sys.argv[1]
ATTR_COLS = [
    "Advisor", "Designer", "Decider", "Champion", "Trainer", "Tester",
    "Influencer", "Customer", "Expert", "Informed", "Consulted",
]
def clean_name(raw):
    s = str(raw or "").strip()
    if not s: return ""
    if "," in s:
        last, first = [x.strip() for x in s.split(",", 1)]
        first = re.sub(r"\\d+$", "", first).strip()
        s = f"{first} {last}".strip()
    else:
        s = " ".join(re.sub(r"(\\D+)\\d+$", r"\\1", p) for p in s.split())
    return re.sub(r"\\s+", " ", s).strip()
def slugify(name):
    return re.sub(r"[^a-z0-9]+", "-", name.lower()).strip("-")
def is_yes(v):
    if v is None: return False
    return str(v).strip().lower() in {"y", "yes", "true", "1", "x"}
wb = openpyxl.load_workbook(path, data_only=True)
ws = wb.active
rows = list(ws.iter_rows(values_only=True))
headers = [str(h).strip() if h else "" for h in rows[0]]
out = {}
for row in rows[1:]:
    if not row or not row[0]: continue
    data = {headers[i]: row[i] if i < len(row) else None for i in range(len(headers))}
    name = clean_name(data.get("Name"))
    if not name or name.lower() in {"tbc", "other tbc"}: continue
    if name.lower().startswith("as required"): continue
    tags = [c.lower() for c in ATTR_COLS if is_yes(data.get(c))]
    if tags:
        out[slugify(name)] = tags
print(json.dumps(out))
`;
  const result = spawnSync('python3', ['-c', py, xlsxPath], {
    encoding: 'utf8',
    maxBuffer: 2 * 1024 * 1024,
  });
  if (result.status !== 0) {
    console.warn('Could not read stakeholder xlsx (optional):', result.stderr?.trim() || result.error);
    return bySlug;
  }
  try {
    const parsed = JSON.parse(result.stdout.trim() || '{}');
    for (const [slug, tags] of Object.entries(parsed)) {
      if (Array.isArray(tags) && tags.length) bySlug.set(slug, tags);
    }
  } catch {
    console.warn('Could not parse stakeholder attribute JSON from Python');
  }
  return bySlug;
};

const rows = parseCsv(fs.readFileSync(csvPath, 'utf8'));
const categories = loadStakeholderCategories();
const responsibilities = loadResponsibilityTags();
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
  const tags = [];
  if (responsibilities.has(id)) tags.push(...responsibilities.get(id));
  if (categories.has(id)) tags.push(categories.get(id));
  const person = { id, name, role, groups };
  const cleanTags = uniq(tags).filter((t) => ATTRIBUTE_LABELS[t]);
  if (cleanTags.length) person.tags = cleanTags;
  seen.set(id, person);
  people.push(person);
}
people.sort((a, b) => a.name.localeCompare(b.name, 'en-GB'));

const data = {
  meta: {
    note: 'Names, roles, and project attributes only — no emails. Source files under documents/people/lists/ (gitignored).',
    lastReviewed: new Date().toISOString().slice(0, 10),
    attributeLabels: ATTRIBUTE_LABELS,
    groups: {
      'project-board': {
        label: 'Project Board',
        blurb:
          'The Project Board provides senior oversight of the Housing Digital Transformation programme. It is responsible for strategic direction, prioritisation, and decisions on investment, resources, and escalation.',
        sort: 1,
      },
      'steering-group': {
        label: 'Steering Group',
        blurb:
          'The Steering Group brings together service leadership to review progress, surface operational issues, and advise the Project Board so that decisions are informed by service-wide knowledge.',
        sort: 2,
      },
      'technical-group': {
        label: 'Technical Group',
        blurb:
          'The Technical Group provides specialist advice on information technology, systems architecture, and data. It meets as required when technical detail is needed to support design or delivery decisions.',
        sort: 3,
      },
      'business-group': {
        label: 'Business Group',
        blurb:
          'The Business Group draws on specialist expertise from across the Council. It meets as required to inform business requirements and the impact of change on services.',
        sort: 4,
      },
    },
  },
  people,
};
fs.writeFileSync(outPath, JSON.stringify(data, null, 2) + '\n');
const withTags = people.filter((p) => p.tags?.length).length;
console.log(
  'Wrote',
  outPath,
  `(${people.length} people, ${withTags} with tags, ${responsibilities.size} responsibility matches)`
);
