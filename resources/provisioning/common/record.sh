# DeveloperJet recipe: final summary (what runs on the server now).
echo "Install finished on $(hostname) at $(date -u +%Y-%m-%dT%H:%M:%SZ)."
if command -v docker >/dev/null 2>&1; then
  docker ps --format '{{.Names}}  {{.Status}}' 2>/dev/null | head -10
fi
