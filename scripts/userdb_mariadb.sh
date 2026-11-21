#!/usr/bin/env bash
set -euo pipefail

db_dir="${FLOX_ENV_CACHE:?Activate the project Flox environment first}/mariadb"
project_dir="${FLOX_ENV_PROJECT:?}"
socket_path="${USERDB_MARIADB_SOCKET:?Activate the project Flox environment first}"
umask 077
mkdir -p "$db_dir/tmp" "$FLOX_ENV_CACHE/logs"

mkdir -p "$(dirname "$socket_path")"

if [ ! -d "$db_dir/data/mysql" ]; then
    TMPDIR="$db_dir/tmp" mariadb-install-db --no-defaults --datadir="$db_dir/data" \
        --auth-root-authentication-method=socket --skip-test-db \
        > "$FLOX_ENV_CACHE/logs/mariadb-init.log" 2>&1 || {
        cat "$FLOX_ENV_CACHE/logs/mariadb-init.log" >&2
        exit 1
    }
fi

exec mariadbd --no-defaults \
    --datadir="$db_dir/data" \
    --tmpdir="$db_dir/tmp" \
    --socket="$socket_path" \
    --pid-file="$db_dir/mariadb.pid" \
    --log-error="$FLOX_ENV_CACHE/logs/mariadb.log" \
    --skip-networking --skip-name-resolve \
    --innodb-buffer-pool-size=128M --max-allowed-packet=128M \
    --sql-mode=ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION \
    --init-file="$project_dir/scripts/userdb_mariadb_init.sql"
