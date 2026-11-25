# DeveloperJet action: restart n8n and its HTTPS proxy (data is kept).
set -e
cd /opt/developerjet/n8n
docker compose restart
docker compose ps --format '{{.Name}}  {{.Status}}'
