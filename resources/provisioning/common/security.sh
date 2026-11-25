# DeveloperJet recipe: safe security baseline. Keeps SSH reachable if the ufw firewall is on and reports the state.
# It never enables/disables the firewall or changes SSH settings (the customer owns the server after handover).
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
  ufw allow 22/tcp >/dev/null
  echo "Firewall (ufw) is active; SSH 22 stays allowed."
else
  echo "ufw firewall is not active."
fi
if systemctl is-active --quiet fail2ban 2>/dev/null; then echo "fail2ban is running."; fi
echo "Kernel: $(uname -r), uptime: $(uptime -p)"
