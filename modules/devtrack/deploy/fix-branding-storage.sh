#!/usr/bin/env bash
# Fix branding images on production: /storage/ must hit Laravel, not the Vue SPA.
# Safe to re-run. Does not wipe MySQL/Redis.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DOMAIN="${DOMAIN:-notifications.africacdc.org}"
DATA_PATH="${EMAIL_SERVER_DATA_PATH:-/home/email_serverdata}"

echo "==> Ensuring storage link + public branding dir"
sudo mkdir -p "${DATA_PATH}/storage/app/public/branding"
# Seed defaults from repo if present and missing on volume
if [[ -d "${ROOT}/backend/storage/app/public/branding" ]]; then
  for f in logo.png logo-dark.png; do
    if [[ -f "${ROOT}/backend/storage/app/public/branding/${f}" \
      && ! -f "${DATA_PATH}/storage/app/public/branding/${f}" ]]; then
      sudo cp -a "${ROOT}/backend/storage/app/public/branding/${f}" \
        "${DATA_PATH}/storage/app/public/branding/${f}"
      echo "    seeded ${f}"
    fi
  done
fi
sudo chown -R 33:33 "${DATA_PATH}/storage" || true
sudo ln -sfn "${DATA_PATH}/storage/app/public" "${ROOT}/backend/public/storage"
ls -la "${DATA_PATH}/storage/app/public/branding/" || true

echo "==> Updating host Nginx (adds /storage/ → API)"
sudo cp "${ROOT}/deploy/configs/nginx-security-headers.conf" \
  /etc/nginx/snippets/email-server-security-headers.conf
TMP="$(mktemp)"
sed "s/notifications\.africacdc\.org/${DOMAIN}/g" \
  "${ROOT}/deploy/configs/nginx-notifications.africacdc.org.conf" > "$TMP"
sudo cp "$TMP" "/etc/nginx/sites-available/${DOMAIN}.conf"
rm -f "$TMP"
sudo ln -sfn "/etc/nginx/sites-available/${DOMAIN}.conf" \
  "/etc/nginx/sites-enabled/${DOMAIN}.conf"
sudo nginx -t
sudo systemctl reload nginx

# Re-attach SSL if Certbot already has a cert (template is HTTP-only)
if [[ -d "/etc/letsencrypt/live/${DOMAIN}" ]]; then
  echo "==> Re-applying Certbot SSL to site"
  sudo certbot --nginx -d "$DOMAIN" --agree-tos --redirect \
    --non-interactive --keep-until-expiring \
    -m "${CERTBOT_EMAIL:-andrewa@africacdc.org}" || true
fi

echo "==> Recreating app + frontend + nginx (API asset route + /storage proxy)"
cd "${ROOT}/docker"
docker compose up -d --force-recreate --no-deps app frontend nginx
docker compose exec -T app php artisan storage:link --force || true
docker compose exec -T app php artisan config:clear || true
docker compose exec -T app php artisan route:clear || true

echo "==> Probe branding asset via API (should be image/png)"
curl -sI "https://${DOMAIN}/api/v1/branding/assets/branding/logo.png" | head -n 8 || true
echo
curl -s "https://${DOMAIN}/api/v1/branding" | head -c 400 || true
echo
echo "Done. Re-upload branding logos if files are missing under:"
echo "  ${DATA_PATH}/storage/app/public/branding/"
ls -la "${DATA_PATH}/storage/app/public/branding/" 2>/dev/null || true
