# DeveloperJet recipe: Docker Engine + Compose plugin via Docker's official convenience script (skipped if present).
set -e
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  echo "Docker already installed: $(docker --version)"
else
  echo "Installing Docker Engine (official get.docker.com script)..."
  curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
  sh /tmp/get-docker.sh >/tmp/get-docker.log 2>&1 || { tail -20 /tmp/get-docker.log; exit 1; }
  rm -f /tmp/get-docker.sh
fi
systemctl enable --now docker >/dev/null 2>&1 || true
docker --version
docker compose version
