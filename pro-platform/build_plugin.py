"""Package the committed Pro Analytics source; never package a live-site edit."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import zipfile

root = Path(__file__).resolve().parent.parent
source = root / 'pro-platform/plugin/westgate-practicum-pro'
parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
subprocess.run(['git', 'diff', '--exit-code', 'HEAD', '--', 'pro-platform'], cwd=root, check=True, capture_output=True)
untracked = subprocess.check_output(['git', 'ls-files', '--others', '--exclude-standard', 'pro-platform'], cwd=root, text=True)
if untracked.strip():
    raise SystemExit('Commit Pro Analytics files before building a release.')
commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root, text=True).strip()
files = sorted(path for path in source.rglob('*') if path.is_file())
version = re.search(r'\* Version:\s*(\S+)', (source / 'westgate-practicum-pro.php').read_text()).group(1)
manifest = {'version': version, 'source_commit': commit, 'files': {str(p.relative_to(source)): hashlib.sha256(p.read_bytes()).hexdigest() for p in files}}
args.output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(args.output, 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in files:
        archive.write(path, str(Path('westgate-practicum-pro') / path.relative_to(source)))
    archive.writestr('westgate-practicum-pro/RELEASE-MANIFEST.json', json.dumps(manifest, indent=2) + '\n')
with zipfile.ZipFile(args.output) as archive:
    assert archive.testzip() is None
    for name, digest in manifest['files'].items():
        assert hashlib.sha256(archive.read('westgate-practicum-pro/' + name)).hexdigest() == digest
print(json.dumps({'version': version, 'source_commit': commit, 'files': len(files), 'zip_verified': True}))
