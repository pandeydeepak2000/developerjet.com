# DeveloperJet action: reinstall fresh, step 1 — remove Hermes CLI and all its data for root (~/.hermes).
set -e
systemctl stop hermes-gateway >/dev/null 2>&1 || true
rm -rf "$HOME/.hermes"
rm -f "$HOME/.local/bin/hermes" "$HOME/.local/bin/hermes-acp" "$HOME/.local/bin/hermes-agent"
echo "Hermes CLI and its data were deleted."
