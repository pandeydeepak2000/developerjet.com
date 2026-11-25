# DeveloperJet recipe: wait until the n8n editor answers over HTTPS (Caddy fetches the certificate on first start,
# n8n runs its first-start database setup). Passes only when readiness AND the editor page both return 200.
D={domain}
echo "Waiting for https://$D (HTTPS certificate + n8n first start, up to 5 minutes)..."
for i in $(seq 1 60); do
  ready=$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 "https://$D/healthz/readiness" || true)
  page=$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 "https://$D/" || true)
  if [ "$ready" = "200" ] && [ "$page" = "200" ]; then
    echo "OK: https://$D is live (valid HTTPS certificate, n8n editor ready)."
    exit 0
  fi
  [ $((i % 6)) -eq 0 ] && echo "still waiting... (readiness ${ready:-none}, editor ${page:-none})"
  sleep 5
done
echo "n8n did not answer on https://$D in time."
echo "Check: does the DNS A record of $D point to this server, and are ports 80/443 open at the provider?"
cd /opt/developerjet/n8n 2>/dev/null && docker compose logs --tail 15 caddy 2>&1 | tail -15
exit 4
