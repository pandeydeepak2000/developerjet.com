# DeveloperJet action: reboot the server. Returns at once; the reboot starts 2 seconds later (so SSH can close cleanly).
nohup sh -c 'sleep 2; systemctl reboot' >/dev/null 2>&1 &
echo "Reboot requested. The server will be back in about 1-2 minutes."
