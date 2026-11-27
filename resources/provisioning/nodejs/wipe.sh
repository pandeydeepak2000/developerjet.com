# DeveloperJet action: reinstall fresh, step 1 — remove all PM2 apps and the app folder /var/www/app.
# Node.js, PM2 and Caddy stay installed; the install step then puts a fresh starter app back.
pm2 delete all >/dev/null 2>&1 || true
pm2 save --force >/dev/null 2>&1 || true
rm -rf /var/www/app
echo "All Node.js apps and /var/www/app were deleted."
