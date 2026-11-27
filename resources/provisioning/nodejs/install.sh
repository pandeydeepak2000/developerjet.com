# DeveloperJet recipe: Node.js hosting stack — Node.js 22 LTS (NodeSource), PM2 process manager, Git and Caddy
# (automatic HTTPS reverse proxy). The customer deploys their own app; a starter app is placed in /var/www/app.
# Safe to run again.
set -eo pipefail
export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a

if ! command -v node >/dev/null 2>&1 || ! node -v | grep -q '^v22\.'; then
  echo "Installing Node.js 22 LTS (NodeSource)..."
  curl -fsSL https://deb.nodesource.com/setup_22.x -o /tmp/nodesource_setup.sh
  bash /tmp/nodesource_setup.sh >/tmp/nodesource.log 2>&1 || { tail -20 /tmp/nodesource.log; exit 1; }
  apt-get -o DPkg::Lock::Timeout=300 install -y -qq nodejs >/dev/null
  rm -f /tmp/nodesource_setup.sh
fi
echo "Node.js $(node -v), npm $(npm -v)"

if ! command -v pm2 >/dev/null 2>&1; then
  echo "Installing PM2..."
  npm install -g pm2 --silent >/dev/null 2>&1
fi
echo "PM2 $(pm2 -v 2>/dev/null | tail -1)"

if ! command -v caddy >/dev/null 2>&1; then
  echo "Installing Caddy (official apt repository)..."
  apt-get -o DPkg::Lock::Timeout=300 install -y -qq debian-keyring debian-archive-keyring apt-transport-https gnupg >/dev/null
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --batch --yes --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
  apt-get -o DPkg::Lock::Timeout=300 update -qq
  apt-get -o DPkg::Lock::Timeout=300 install -y -qq caddy >/dev/null
fi
echo "$(caddy version 2>/dev/null | cut -d' ' -f1 | sed 's/^/Caddy /')"

# Starter app (only if the customer has nothing there yet), kept alive by PM2.
mkdir -p /var/www/app
if [ ! -f /var/www/app/package.json ] && [ ! -f /var/www/app/server.js ]; then
  cat > /var/www/app/server.js <<'EOF'
const http = require('http');
const port = process.env.PORT || 3000;
http.createServer((req, res) => {
  res.writeHead(200, { 'Content-Type': 'text/plain' });
  res.end('Your Node.js server is running. Replace /var/www/app with your app.\n');
}).listen(port, '127.0.0.1', () => console.log(`Listening on ${port}`));
EOF
fi
cd /var/www/app
if [ -f server.js ] && ! pm2 describe app >/dev/null 2>&1; then
  pm2 start server.js --name app >/dev/null
fi
pm2 save >/dev/null 2>&1 || true
if pidof systemd >/dev/null 2>&1; then
  pm2 startup systemd -u root --hp /root >/dev/null 2>&1 || true
  systemctl enable --now pm2-root >/dev/null 2>&1 || true
fi
pm2 ls --no-color 2>/dev/null | grep -E 'app|name' | head -3 || true
