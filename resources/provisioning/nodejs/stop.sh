# DeveloperJet action: suspend — stop all PM2 apps and the Caddy web server (files are kept).
pm2 stop all >/dev/null 2>&1 || true
systemctl stop caddy >/dev/null 2>&1 || true
echo "All Node.js apps and the web server are stopped."
