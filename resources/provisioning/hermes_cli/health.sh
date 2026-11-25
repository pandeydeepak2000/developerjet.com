# DeveloperJet recipe: Hermes CLI health check (the launcher exists and answers).
set -e
export PATH="$HOME/.local/bin:$PATH"
command -v hermes >/dev/null 2>&1 || { echo "hermes command not found"; exit 4; }
hermes --version 2>&1 | head -3
echo "Hermes CLI is installed. The customer adds their AI key with: hermes config set OPENROUTER_API_KEY <key>"
