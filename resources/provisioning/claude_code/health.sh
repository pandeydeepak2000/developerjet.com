# DeveloperJet recipe: Claude Code health check (the CLI exists and answers).
set -e
export PATH="$HOME/.local/bin:$PATH"
command -v claude >/dev/null 2>&1 || { echo "claude command not found"; exit 4; }
claude --version 2>&1 | head -2
echo "Claude Code is installed. The customer signs in with: claude auth login"
