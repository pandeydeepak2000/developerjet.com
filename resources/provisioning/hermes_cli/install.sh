# DeveloperJet recipe: Hermes Agent CLI (Nous Research, MIT) via the official installer, non-interactive.
# Installs for root: code in ~/.hermes/hermes-agent, launcher ~/.local/bin/hermes. Safe to run again.
set -eo pipefail
export PATH="$HOME/.local/bin:$PATH"
if command -v hermes >/dev/null 2>&1; then
  echo "Hermes is already installed; updating via the official installer."
fi
curl -fsSL https://hermes-agent.nousresearch.com/install.sh -o /tmp/hermes-install.sh
bash /tmp/hermes-install.sh --non-interactive 2>&1
rm -f /tmp/hermes-install.sh
grep -q '.local/bin' "$HOME/.bashrc" 2>/dev/null || echo 'export PATH="$HOME/.local/bin:$PATH"' >> "$HOME/.bashrc"
