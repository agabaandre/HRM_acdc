# APM Docker (Laravel Sail fragments)

The `8.x/` Dockerfiles here are **Laravel Sail** leftovers. They are **not** used for CBP local development.

Use the unified stack at the **repository root**:

```bash
cd /path/to/staff
cp docker/compose.env.example .env
./docker/bootstrap.sh
```

APM runs at `http://localhost:8080/apm/` and uses the shared **`staff`** database (`apm_migrations` table for migration tracking).
