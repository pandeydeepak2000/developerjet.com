# DeveloperJet action: unsuspend — start the web server and all PM2 apps again.
systemctl start caddy >/dev/null 2>&1 || true
pm2 resurrect >/dev/null 2>&1 || pm2 start all >/dev/null 2>&1 || true
pm2 ls --no-color | head -12
