# DeveloperJet action: unsuspend — start n8n and its HTTPS proxy again.
set -e
cd /opt/developerjet/n8n
docker compose up -d
docker compose ps --format '{{.Name}}  {{.Status}}'
