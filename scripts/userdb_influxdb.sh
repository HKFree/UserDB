#!/usr/bin/env bash
set -euo pipefail

influx_dir="${FLOX_ENV_CACHE:?Activate the project Flox environment first}/influxdb"
umask 077
mkdir -p "$influx_dir" "$FLOX_ENV_CACHE/logs"

# Port 0 asks the OS for a free port. The integration test reads the listening
# address from this worktree's log and provisions its own database via the API.
exec influxd \
    --http-bind-address=127.0.0.1:0 \
    --bolt-path="$influx_dir/influxd.bolt" \
    --sqlite-path="$influx_dir/influxd.sqlite" \
    --engine-path="$influx_dir/engine" \
    --reporting-disabled \
    > "$FLOX_ENV_CACHE/logs/influxdb.log" 2>&1
