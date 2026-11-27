# DeveloperJet recipe: Node.js stack health — node, npm, PM2 and Caddy answer, and the starter app responds.
set -e
node -v
npm -v
pm2 -v | tail -1
caddy version | cut -d' ' -f1
for i in 1 2 3 4 5 6; do
  if curl -fsS --max-time 5 http://127.0.0.1:3000/ >/dev/null 2>&1; then
    echo "Starter app answers on 127.0.0.1:3000 (managed by PM2)."
    exit 0
  fi
  sleep 2
done
pm2 describe app >/dev/null 2>&1 && echo "PM2 is running your app." && exit 0
echo "The starter app did not answer."; exit 4
