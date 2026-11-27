# DeveloperJet: read-only usage snapshot for the customer panel. Changes nothing. Prints key=value lines.
# CPU and network rates are measured over one second.
set -u
iface=$(ip route show default 2>/dev/null | awk '{for(i=1;i<NF;i++) if($i=="dev"){print $(i+1); exit}}')
net=""
if [ -n "$iface" ] && [ -r "/sys/class/net/$iface/statistics/rx_bytes" ]; then net="/sys/class/net/$iface/statistics"; fi
read -r _ u1 n1 s1 i1 w1 q1 sq1 st1 _ < /proc/stat
if [ -n "$net" ]; then rx1=$(cat "$net/rx_bytes"); tx1=$(cat "$net/tx_bytes"); fi
sleep 1
read -r _ u2 n2 s2 i2 w2 q2 sq2 st2 _ < /proc/stat
busy=$(( (u2 + n2 + s2 + q2 + sq2 + st2) - (u1 + n1 + s1 + q1 + sq1 + st1) ))
idle=$(( (i2 + w2) - (i1 + w1) ))
total=$(( busy + idle ))
if [ "$total" -gt 0 ]; then echo "cpu_permille=$(( busy * 1000 / total ))"; else echo "cpu_permille=0"; fi
echo "cpu_cores=$(nproc)"
echo "load=$(cut -d' ' -f1-3 /proc/loadavg)"
awk '/^MemTotal:/{t=$2} /^MemAvailable:/{a=$2} /^SwapTotal:/{st=$2} /^SwapFree:/{sf=$2} END{print "mem_total_kb=" t; print "mem_used_kb=" t-a; print "swap_total_kb=" st; print "swap_used_kb=" st-sf}' /proc/meminfo
df -Pk / | awk 'NR==2{print "disk_total_kb=" $2; print "disk_used_kb=" $3}'
echo "uptime_s=$(cut -d. -f1 /proc/uptime)"
if [ -n "$net" ]; then
    rx2=$(cat "$net/rx_bytes"); tx2=$(cat "$net/tx_bytes")
    echo "net_rx_bytes=$rx2"
    echo "net_tx_bytes=$tx2"
    if [ "$rx2" -ge "$rx1" ]; then echo "net_rx_rate=$(( rx2 - rx1 ))"; fi
    if [ "$tx2" -ge "$tx1" ]; then echo "net_tx_rate=$(( tx2 - tx1 ))"; fi
fi
if [ -r /etc/os-release ]; then . /etc/os-release; echo "os=${PRETTY_NAME:-Linux}"; fi
echo "hostname=$(hostname)"
