#!/usr/bin/env bash
# Install repo git hooks. Run from repo root.
set -e
hooks_src_dir="$(dirname "$0")"
hooks_dst_dir=".git/hooks"
[ -d "$hooks_dst_dir" ] || { echo "Not a git repo (no .git/hooks)"; exit 1; }
for hook in pre-commit; do
    cp "$hooks_src_dir/$hook" "$hooks_dst_dir/$hook"
    chmod +x "$hooks_dst_dir/$hook"
    echo "Installed $hook"
done
echo "Done. To uninstall: rm $hooks_dst_dir/pre-commit"
