# DeveloperJet recipe: n8n (self-hosted) with Caddy for automatic HTTPS.
# Rendered by RecipeRegistry: {domain} is a validated, shell-quoted hostname. Safe to run again (idempotent).
set -e
D={domain}
APP=/opt/developerjet/n8n

# Ports 80/443 must be free (or already ours) so Caddy can get the HTTPS certificate.
for p in 80 443; do
  if ss -ltnH "( sport = :$p )" | grep -q . && ! docker ps --format '{{.Names}}' | grep -qx 'developerjet-n8n-caddy'; then
    echo "Port $p is already used by another program on this server:"
    ss -ltnpH "( sport = :$p )" | head -3
    echo "Stop that program (or use a clean VPS) and press Deploy again."
    exit 3
  fi
done

mkdir -p "$APP"
cd "$APP"

cat > Caddyfile <<EOF
$D {
    reverse_proxy n8n:5678
}
EOF

cat > compose.yaml <<EOF
name: developerjet-n8n
services:
  n8n:
    image: docker.n8n.io/n8nio/n8n:latest
    container_name: developerjet-n8n
    restart: always
    environment:
      - N8N_HOST=$D
      - N8N_PORT=5678
      - N8N_PROTOCOL=https
      - N8N_PROXY_HOPS=1
      - NODE_ENV=production
      - N8N_WEBHOOK_URL=https://$D/
      - GENERIC_TIMEZONE=Asia/Kolkata
      - TZ=Asia/Kolkata
      - N8N_ENFORCE_SETTINGS_FILE_PERMISSIONS=true
    volumes:
      - n8n_data:/home/node/.n8n
  caddy:
    image: caddy:2
    container_name: developerjet-n8n-caddy
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
volumes:
  n8n_data:
  caddy_data:
  caddy_config:
EOF

echo "Downloading n8n and Caddy (first time takes a minute)..."
docker compose pull -q
docker compose up -d --remove-orphans
docker compose ps --format '{{.Name}}  {{.Status}}'
echo "n8n files: $APP"
