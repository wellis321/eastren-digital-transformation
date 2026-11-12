"""Build an offline copy of the Project Hub guide that opens straight from disk.

Run:  python3 scripts/build-project-hub-standalone.py
Output: standalone-project-hub/ and standalone-project-hub.zip (both gitignored).

Unzip anywhere and double-click index.html - no server, no login. The site
stylesheet and logo are copied in, server-only links (portal home, sign out)
are removed, and main.js is swapped for the one bit the guide needs (the date).
"""
import os
import re
import shutil
import zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.normpath(os.path.join(HERE, ".."))
SRC = os.path.join(ROOT, "project-hub")
NAME = "standalone-project-hub"
OUT = os.path.join(ROOT, NAME)
ZIP = OUT + ".zip"

DATE_SCRIPT = """<script>
(function () {
    var d = document.getElementById('current-date');
    if (d) d.textContent = new Date().toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
})();
</script>"""

if os.path.isdir(OUT):
    shutil.rmtree(OUT)
os.makedirs(os.path.join(OUT, "assets", "css"))
os.makedirs(os.path.join(OUT, "assets", "media"))

shutil.copy(os.path.join(ROOT, "assets", "css", "style.css"), os.path.join(OUT, "assets", "css", "style.css"))
shutil.copy(os.path.join(ROOT, "assets", "media", "leaves.png"), os.path.join(OUT, "assets", "media", "leaves.png"))
shutil.copytree(os.path.join(SRC, "img"), os.path.join(OUT, "img"))
for f in ("guide.css", "search-index.js"):
    shutil.copy(os.path.join(SRC, f), os.path.join(OUT, f))

# guide.js: drop the "Back to the portal" link - there's no portal offline.
js = open(os.path.join(SRC, "guide.js"), encoding="utf-8").read()
js = js.replace("""+ '<a class="ph-back" href="../index.html">← Back to the portal</a>'""", "+ ''")
# ...and the "Private - only you can see this" badge, which isn't true of a copy.
js, n = re.subn(r"\+ '<span class=\"ph-private\">.*?</span>'", "+ ''", js)
assert n == 1, "private badge not found in guide.js"
assert "../" not in js, "guide.js still points outside the guide"
open(os.path.join(OUT, "guide.js"), "w", encoding="utf-8").write(js)

pages = sorted(f for f in os.listdir(SRC) if f.endswith(".html"))
for fname in pages:
    doc = open(os.path.join(SRC, fname), encoding="utf-8").read()
    doc = doc.replace('href="../index.html" class="topbar-left"', 'href="index.html" class="topbar-left"')
    doc = re.sub(r'\s*<a href="\.\./logout\.php"[^>]*>.*?</a>', "", doc)
    doc = doc.replace('<script src="../assets/js/main.js"></script>', DATE_SCRIPT)
    doc = doc.replace('"../assets/', '"assets/')
    leftover = re.findall(r'(?:href|src)="\.\./[^"]*"', doc)
    assert not leftover, f"{fname} still has server paths: {leftover}"
    open(os.path.join(OUT, fname), "w", encoding="utf-8").write(doc)

if os.path.exists(ZIP):
    os.remove(ZIP)
with zipfile.ZipFile(ZIP, "w", zipfile.ZIP_DEFLATED) as z:
    for base, _, files in os.walk(OUT):
        for f in sorted(files):
            full = os.path.join(base, f)
            z.write(full, os.path.join(NAME, os.path.relpath(full, OUT)))

print(f"{len(pages)} pages -> {OUT}")
print(f"zip: {ZIP} ({os.path.getsize(ZIP) / 1024 / 1024:.1f} MB)")
