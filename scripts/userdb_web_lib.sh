#!/usr/bin/env bash
# Starting the local web of a checkout and stopping what was started. Sourced by
# scripts/userdb_screenshots.sh and scripts/userdb_e2e.sh; the caller must use `set -m`, so that
# every launched command gets its own process group, including Flox's children.

# The callers read the results from these variables.
# shellcheck disable=SC2034

# Process groups of the commands launched and not yet waited for.
groups=
# Single processes that were told to stop and may have left their wrapper's process group.
remaining=
launched_pid=
web_origin=
failure_reason=

running_services() {
    # pgrep -fl prints no command arguments with Linux procps.
    # shellcheck disable=SC2009
    ps ax -o pid=,command= | grep -E 'mariadbd|php -S' | grep -F -- "$1/"
}

launch() {
    local directory=$1 logfile=$2
    shift 2
    (cd "$directory" && exec "$@") </dev/null >"$logfile" 2>&1 &
    launched_pid=$!
    groups="$groups $launched_pid"
}

wait_job() {
    local waited_pid=$1 pid status=0 running=
    wait "$waited_pid" || status=$?
    for pid in $groups; do
        if [ "$pid" != "$waited_pid" ]; then running="$running $pid"; fi
    done
    groups=$running
    return "$status"
}

web_is_running() {
    local wrapper_pid=$1 pidfile=$2 pid
    kill -0 "$wrapper_pid" 2>/dev/null || return 1
    [ -f "$pidfile" ] || return 0
    pid=$(cat "$pidfile")
    kill -0 "$pid" 2>/dev/null
}

# start_web <directory> <logfile> <pidfile> [<command that runs the server in the project's environment>...]
# Sets web_origin; on failure sets failure_reason.
start_web() {
    local directory=$1 logfile=$2 pidfile=$3 deadline status web_pid
    local started='.*Development Server \((http://127\.0\.0\.1:[0-9]+)\) started.*'
    shift 3
    failure_reason=
    web_origin=
    # The PID and arguments must be expanded by the shell that runs the server.
    # shellcheck disable=SC2016
    launch "$directory" "$logfile" \
        "$@" bash --noprofile --norc -e -o pipefail -c \
        'echo $$ > "$1"; shift; unset PHP_CLI_SERVER_WORKERS; exec php "$@"' userdb-web \
        "$pidfile" -S 127.0.0.1:0 -t "$directory/www" "$directory/scripts/userdb_dev_router.php"
    web_pid=$launched_pid
    deadline=$((SECONDS + 60))
    while [ "$SECONDS" -lt "$deadline" ]; do
        if ! web_is_running "$web_pid" "$pidfile"; then
            echo "The web process exited before becoming ready. See $logfile" >&2
            wait_job "$web_pid" || true
            failure_reason=setup-failed
            return 1
        fi
        web_origin=$(sed -nE "s|$started|\1|p" "$logfile" | head -n 1)
        if [ -n "$web_origin" ]; then
            status=$(curl --silent --max-time 5 --output /dev/null --write-out '%{http_code}' "$web_origin/" || true)
            if [ "$status" = 200 ] && web_is_running "$web_pid" "$pidfile"; then
                return 0
            fi
        fi
        sleep 1
    done
    failure_reason=web-timeout
    return 1
}

stop_process_groups() {
    local pid
    # Stop the process groups first so no wrapper can start another service during cleanup.
    for pid in $groups; do
        # These jobs are being stopped deliberately. Remove only their shell job notifications.
        disown "$pid" 2>/dev/null || true
        kill -TERM -- "-$pid" 2>/dev/null
    done
}

# stop_detached_service <pidfile> <directory>: a service may have moved out of its wrapper's process group.
stop_detached_service() {
    local file=$1 directory=$2 pid
    [ -f "$file" ] || return 0
    pid=$(cat "$file")
    case "$pid" in ''|*[!0-9]*) return 0 ;; esac
    # The PID may have been reused: stop it only while its command line still names this checkout.
    if ps -p "$pid" -o command= | grep -F -- "$directory/" >/dev/null; then
        kill -TERM "$pid" 2>/dev/null
        remaining="$remaining $pid"
    fi
}

wait_for_services() {
    local pid alive
    for _ in $(seq 10); do
        alive=false
        for pid in $groups; do kill -0 -- "-$pid" 2>/dev/null && alive=true; done
        for pid in $remaining; do kill -0 "$pid" 2>/dev/null && alive=true; done
        [ "$alive" = true ] || break
        sleep 1
    done
    for pid in $groups; do kill -KILL -- "-$pid" 2>/dev/null; done
    for pid in $remaining; do kill -KILL "$pid" 2>/dev/null; done
}
