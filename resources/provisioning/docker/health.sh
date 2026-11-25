# DeveloperJet recipe: Docker VPS health check (engine running + compose available + a container can start).
set -e
systemctl is-active --quiet docker
docker info --format 'Docker engine {{.ServerVersion}} is running.'
docker compose version
docker run --rm hello-world >/dev/null && echo "Test container ran successfully."
