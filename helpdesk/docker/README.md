# Helpdesk Docker

Do **not** run a separate Compose stack here.

Use the **repository root** Docker setup:

```bash
cd /path/to/staff
cp docker/compose.env.example .env
./docker/bootstrap.sh
```

All modules (including Helpdesk) connect to the shared **`staff`** MySQL database.

Optional Redis for queues/cache:

```bash
docker compose --profile redis up -d
```

Then set `REDIS_HOST=redis` in `helpdesk/backend/.env`.
