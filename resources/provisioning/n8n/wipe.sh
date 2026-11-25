# DeveloperJet action: reinstall fresh, step 1 — delete n8n completely (containers, ALL n8n data and certificates).
# Only touches what DeveloperJet installed (/opt/developerjet/n8n, compose project "developerjet-n8n").
set -e
if [ -d /opt/developerjet/n8n ]; then
  cd /opt/developerjet/n8n
  docker compose down -v --remove-orphans
  cd /
  rm -rf /opt/developerjet/n8n
  echo "n8n and all its data were deleted."
else
  echo "n8n is not installed here; nothing to delete."
fi
