# DeveloperJet recipe: Claude Code CLI via Anthropic's official installer (customer signs in with their own account
# or API key afterwards; DeveloperJet never provides Claude access). Safe to run again.
set -eo pipefail
export PATH="$HOME/.local/bin:$PATH"
curl -fsSL https://claude.ai/install.sh -o /tmp/claude-install.sh
bash /tmp/claude-install.sh 2>&1
rm -f /tmp/claude-install.sh
grep -q '.local/bin' "$HOME/.bashrc" 2>/dev/null || echo 'export PATH="$HOME/.local/bin:$PATH"' >> "$HOME/.bashrc"
