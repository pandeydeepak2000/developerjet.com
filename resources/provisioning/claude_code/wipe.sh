# DeveloperJet action: reinstall fresh, step 1 — remove Claude Code and its settings/sign-in for root.
set -e
rm -f "$HOME/.local/bin/claude"
rm -rf "$HOME/.local/share/claude" "$HOME/.claude" "$HOME/.claude.json"
echo "Claude Code and its settings were deleted."
