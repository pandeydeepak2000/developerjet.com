# DeveloperJet action: forgotten n8n password — n8n's official "user-management:reset". The login is reset so the
# owner account can be created again on the next visit; workflows and credentials are kept (verified 2026-11-25).
set -e
docker exec -u node developerjet-n8n n8n user-management:reset 2>&1 | tail -1
cd /opt/developerjet/n8n && docker compose restart n8n >/dev/null 2>&1
echo "Done. Open the n8n address and create the owner account again."
