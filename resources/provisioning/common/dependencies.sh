# DeveloperJet recipe: base packages. Waits for other apt runs (e.g. unattended-upgrades) instead of failing.
set -e
export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a
apt-get -o DPkg::Lock::Timeout=300 update -qq
apt-get -o DPkg::Lock::Timeout=300 install -y -qq curl ca-certificates git >/dev/null
echo "curl, ca-certificates and git are installed."
