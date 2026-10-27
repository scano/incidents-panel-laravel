#!/usr/bin/env sh
set -eu

if [ "${MYSQL_DATABASE:-}" = 'incidencias_testing' ] || [ "${MYSQL_USER:-}" = 'incidencias_test' ]; then
    echo 'La base y el usuario del panel deben ser distintos de los reservados para pruebas.' >&2
    exit 1
fi

# SQL uses doubled quotes and disables backslash escaping for the password literal.
test_password_sql=$(printf '%s' "${MYSQL_TEST_PASSWORD:?Falta MYSQL_TEST_PASSWORD}" | sed "s/'/''/g")
MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?Falta MYSQL_ROOT_PASSWORD}" mysql --user=root <<SQL
SET SESSION sql_mode = 'NO_BACKSLASH_ESCAPES';
CREATE DATABASE IF NOT EXISTS incidencias_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'incidencias_test'@'%' IDENTIFIED BY '${test_password_sql}';
ALTER USER 'incidencias_test'@'%' IDENTIFIED BY '${test_password_sql}';
GRANT ALL PRIVILEGES ON incidencias_testing.* TO 'incidencias_test'@'%';
SQL

echo 'Base MySQL y usuario exclusivos de pruebas preparados.'
