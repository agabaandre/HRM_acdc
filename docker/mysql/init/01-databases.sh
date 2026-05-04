#!/bin/bash
set -e

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
CREATE DATABASE IF NOT EXISTS \`staff_ci\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`apm_local\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`apm_local\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL
