# DeveloperJet action: suspend — stop n8n and its HTTPS proxy (data is kept; start brings it back).
set -e
cd /opt/developerjet/n8n
docker compose stop
echo "n8n is stopped."
