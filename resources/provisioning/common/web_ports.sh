# DeveloperJet recipe: allow web traffic (80/443) when the ufw firewall is in use. Never enables or disables ufw.
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
  ufw allow 22/tcp >/dev/null
  ufw allow 80/tcp >/dev/null
  ufw allow 443/tcp >/dev/null
  echo "Firewall (ufw) active: SSH 22, HTTP 80 and HTTPS 443 are allowed."
else
  echo "ufw firewall is not active; nothing to open."
fi
