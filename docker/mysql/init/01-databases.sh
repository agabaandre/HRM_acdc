#!/bin/bash
set -e

# Single shared schema for all CBP modules (Staff CI, staff-portal, APM, Finance, Helpdesk).
STAFF_DB="${STAFF_DB_NAME:-staff}"

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
CREATE DATABASE IF NOT EXISTS \`${STAFF_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${STAFF_DB}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL
