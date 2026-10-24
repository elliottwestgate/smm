"""Build only a clean revision already published to its tracked Git branch."""
from pathlib import Path
import argparse
import hashlib
import json
import subprocess
import zipfile

PROJECT = Path(__file__).resolve().parent

def git(*args):
    return subprocess.check_output(["git", "-C", str(PROJECT), *args], text=True).strip()

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    if git("status", "--porcelain", "--", "."):
        raise SystemExit("Commit the project changes before building.")
    commit = git("rev-parse", "HEAD")
    try:
        published = git("rev-parse", "@{upstream}")
    except subprocess.CalledProcessError:
        raise SystemExit("Publish this revision and configure its upstream before building.")
    if published != commit:
        raise SystemExit("Refresh and publish the tracked branch before building this commit.")
    tracked = subprocess.check_output(["git", "-C", str(PROJECT), "ls-files", "-z", "--", "plugin/message-matrix-pro"], text=True).split("\0")
    files = [PROJECT / name for name in tracked if name]
    if not files:
        raise SystemExit("No tracked plugin files found.")
    for file in files:
        if file.is_symlink() or not file.is_file():
            raise SystemExit("Only tracked regular plugin files may be packaged.")
    args.output = args.output.resolve()
    if args.output.is_relative_to(PROJECT):
        raise SystemExit("Write release artifacts outside the source project.")
    manifest = {"commit": commit, "files": {str(p.relative_to(PROJECT / "plugin")): hashlib.sha256(p.read_bytes()).hexdigest() for p in files}}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(args.output, "w", zipfile.ZIP_DEFLATED) as archive:
        for file in files:
            archive.write(file, file.relative_to(PROJECT / "plugin"))
        archive.writestr("message-matrix-pro/build-manifest.json", json.dumps(manifest, indent=2))
    print(args.output)

if __name__ == "__main__":
    main()
