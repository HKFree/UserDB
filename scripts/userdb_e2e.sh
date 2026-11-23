#!/usr/bin/env bash
# Browser tests of the main processes (Playwright, tests/e2e) against a local web of this checkout:
#   scripts/userdb_e2e.sh [arguments of "playwright test", e.g. specs/smoke.spec.mjs --headed]
# Needs Flox, which also brings Node, and Composer dependencies. Every run rebuilds the database
# userdb_e2e from the scenario demo data and starts its own web; the developer's database, web, sessions
# and e-mails are never used.
set -euo pipefail
# Job control gives every launched command its own process group, including the web server's.
set -m

scripts_dir=$(cd "$(dirname "$0")" && pwd -P)
# shellcheck source=scripts/userdb_web_lib.sh
source "$scripts_dir/userdb_web_lib.sh"

# The physical path: PHP derives the names of the session and persona cookies from it.
repository=$(cd "$scripts_dir/.." && pwd -P)
tests_dir=$repository/tests/e2e
out=$repository/temp/e2e
lock=
web_pidfile=$out/logs/web.pid
# npm writes this file last; the script compares its time with package-lock.json.
installed=$tests_dir/node_modules/.package-lock.json
installing=

fail() {
    echo "$1" >&2
    exit 1
}

# Installs what package-lock.json names, and its browser, whenever the lock file is newer than the installation.
# As background jobs: a signal then stops a stalled download at once and the cleanup releases the lock.
install_node_packages() {
    local step
    if [ "$installed" -nt "$tests_dir/package-lock.json" ]; then
        return
    fi
    echo 'Installing Playwright...'
    installing=1
    for step in 'npm ci --no-audit --no-fund' 'npx playwright install chromium'; do
        # The step is a command line without quoting.
        # shellcheck disable=SC2086
        launch "$tests_dir" "$out/logs/install.log" $step
        if ! wait_job "$launched_pid"; then
            tail -n 20 "$out/logs/install.log" >&2
            fail "\"$step\" failed. See $out/logs/install.log"
        fi
    done
    installing=
}

wait_for_database() {
    local deadline=$((SECONDS + 60))
    until mariadb-admin --socket="${USERDB_MARIADB_SOCKET:-}" --connect-timeout=2 ping >/dev/null 2>&1; do
        if [ "$SECONDS" -ge "$deadline" ]; then
            fail 'Flox MariaDB is not ready; check .flox/cache/logs/mariadb.log.'
        fi
        sleep 1
    done
}

# Two runs in one worktree would rebuild the database under each other. The lock is a symbolic link
# to the PID of its owner: creating it is atomic and it never exists without the PID.
acquire_lock() {
    local link=$FLOX_ENV_CACHE/e2e.lock other
    if ! ln -s "$$" "$link" 2>/dev/null; then
        other=$(readlink "$link" 2>/dev/null || true)
        case "$other" in ''|*[!0-9]*) fail "Remove the unreadable lock $link and run again." ;; esac
        if kill -0 "$other" 2>/dev/null; then
            fail "Another run of the browser tests (PID $other) is using this worktree. If none is, remove $link."
        fi
        # The previous run was killed before it could clean up: its web may still be running.
        stop_detached_service "$web_pidfile" "$repository"
        wait_for_services
        remaining=
        rm -f "$link"
        ln -s "$$" "$link" 2>/dev/null || fail 'Another run of the browser tests has just started in this worktree.'
    fi
    lock=$link
}

# Invoked by the EXIT trap.
# shellcheck disable=SC2329
cleanup() {
    local status=$? pid
    trap - EXIT INT TERM
    set +e
    set +m
    # Without the lock the web and its PID file belong to another run.
    if [ -n "$lock" ]; then
        stop_process_groups
        # After an unfinished installation the next run would find the packages current and never install the browser.
        [ -z "$installing" ] || rm -f "$installed"
        stop_detached_service "$web_pidfile" "$repository"
        wait_for_services
        pid=$(cat "$web_pidfile" 2>/dev/null)
        case "$pid" in ''|*[!0-9]*) pid= ;; esac
        if [ -n "$pid" ] && ps -p "$pid" -o command= | grep -F -- "$repository/" >/dev/null; then
            echo "Cleanup failed: the web of the browser tests is still running (PID $pid)." >&2
            status=1
        fi
        rm -f "$lock"
    fi
    exit "$status"
}

# Always through an activation of its own, also from a shell where Flox is already active:
# only that makes sure the services are running.
if [ -z "${USERDB_E2E_IN_FLOX:-}" ]; then
    command -v flox >/dev/null || fail 'Flox is required, see "Tests with Flox" in readme.md.'
    # Everything that can fail at once is checked before Flox starts MariaDB: an activation that ends
    # while the database is still starting can leave the service hanging.
    [ -f "$repository/vendor/autoload.php" ] || fail 'Composer dependencies are missing: run scripts/userdb_worktree.sh setup.'
    USERDB_E2E_IN_FLOX=1 exec flox activate -s -d "$repository" -- bash "$scripts_dir/userdb_e2e.sh" "$@"
fi

trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

# First of all, see the comment above: nothing may end this activation before MariaDB has started.
wait_for_database
acquire_lock
rm -rf "$out"
mkdir -p "$out/logs"
# With Node of Flox, and under the lock: two runs would install into one directory.
install_node_packages

echo 'Preparing the database userdb_e2e...'
# A MariaDB started before the init file knew this database only gets it now; the statements are idempotent.
mariadb --socket="$USERDB_MARIADB_SOCKET" < "$scripts_dir/userdb_mariadb_init.sql"
if ! (cd "$repository" && USERDB_LOCAL_INSTANCE=e2e USERDB_DEMO_VOLUME=0 php scripts/userdb_dev_setup.php) \
    > "$out/logs/setup.log" 2>&1; then
    tail -n 20 "$out/logs/setup.log" >&2
    fail "The database setup failed. See $out/logs/setup.log"
fi

if ! start_web "$repository" "$out/logs/web.log" "$web_pidfile" env USERDB_LOCAL_INSTANCE=e2e; then
    fail "The web did not start ($failure_reason). See $out/logs/web.log"
fi
echo "Web of the browser tests: $web_origin"

export USERDB_E2E_ORIGIN=$web_origin
export USERDB_E2E_MAIL_DIR=$FLOX_ENV_CACHE/e2e/mail
# PHP expands its own variables.
# shellcheck disable=SC2016
USERDB_E2E_PERSONA_COOKIE=$(php -r 'require $argv[1] . "/scripts/UserdbDemoPersonas.php"; echo UserdbDemoPersonas::cookieName($argv[1]);' "$repository")
export USERDB_E2E_PERSONA_COOKIE

# In the background and waited for: bash runs the TERM trap only between foreground commands,
# so a foreground Playwright would have to finish before the run could be stopped.
(cd "$tests_dir" && exec npx playwright test "$@") </dev/null &
groups="$groups $!"
status=0
wait_job "$!" || status=$?
exit "$status"
