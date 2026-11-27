# DeveloperJet action: restart every app PM2 manages (data and code are kept).
set -e
pm2 restart all
pm2 ls --no-color | head -12
