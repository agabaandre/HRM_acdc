#!/usr/bin/env bash
# Fix: nginx [emerg] zero size shared memory zone "api_limit"
# (blocks certbot --nginx and nginx -t)
#
#   cd /var/lib/SYSTEMS/email_server && ./deploy/fix-nginx-api-limit.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/deploy/configs/nginx-http-rate-limit.conf"
DEST=/etc/nginx/conf.d/email-server-rate-limit.conf

if [[ ! -f "$SRC" ]]; then
  echo "ERROR: missing $SRC" >&2
  exit 1
fi

echo "==> Who references api_limit?"
sudo grep -RIn "api_limit" /etc/nginx/ 2>/dev/null || echo "(no matches — zone may still be required by a cached include)"

echo "==> Installing $DEST"
sudo mkdir -p /etc/nginx/conf.d
sudo cp "$SRC" "$DEST"

echo "==> nginx -t"
sudo nginx -t

echo "==> reload nginx"
sudo systemctl reload nginx

echo "==> Retry Certbot (optional)"
DOMAIN="${DOMAIN:-notifications.africacdc.org}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-andrewa@africacdc.org}"
if command -v certbot >/dev/null 2>&1; then
  sudo certbot --nginx \
    -d "$DOMAIN" \
    --agree-tos \
    --redirect \
    -m "$CERTBOT_EMAIL" \
    --non-interactive \
    --keep-until-expiring \
    || echo "WARN: certbot still failed — run: sudo grep -R api_limit /etc/nginx/"
else
  echo "certbot not installed; nginx zone fix applied only"
fi

echo "Done."
