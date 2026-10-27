#!/bin/sh
# Runs once, on first start of an empty MySQL volume.
# Creates the database used by the backend test suite and grants the app user access to it.
set -eu

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`testing\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`testing\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
