#!/usr/bin/env bash
set -euo pipefail
# Job control gives every launched command its own process group, including Flox's children.
set -m

# shellcheck source=scripts/userdb_web_lib.sh
source "$(dirname "$0")/userdb_web_lib.sh"

tools_dir=$(cd "$(dirname "$0")/../tests/screenshots" && pwd)
repository=$(git -C "$tools_dir" rev-parse --show-toplevel)
mode=${1:-}
[ "$#" -gt 0 ] && shift
head=
base=
out=
run_dir=
owned_labels=
base_reason=
base_commit=
head_commit=HEAD

usage() {
    echo 'Usage: userdb_screenshots.sh local [<base commit>] [--head <commit>]' >&2
    echo '       userdb_screenshots.sh run --head <directory> [--base <directory>] --out <directory>' >&2
    exit 2
}

stop_detached_services() {
    local label directory
    for label in $owned_labels; do
        if [ "$label" = head ]; then directory=$head; else directory=$base; fi
        [ -n "$directory" ] || continue
        stop_detached_service "$directory/.flox/cache/mariadb/mariadb.pid" "$directory"
        stop_detached_service "$out/logs/$label-web.pid" "$directory"
    done
}

collect_logs_and_check_services() {
    local label directory result=0
    for label in $owned_labels; do
        if [ "$label" = head ]; then directory=$head; else directory=$base; fi
        [ -n "$directory" ] || continue
        if [ -f "$directory/.flox/cache/logs/mariadb.log" ]; then
            cp "$directory/.flox/cache/logs/mariadb.log" "$out/logs/$label-mariadb.log"
        fi
        if running_services "$directory"; then
            echo "Cleanup failed: services still running in $directory" >&2
            result=1
        fi
    done
    return "$result"
}

remove_local_worktrees() {
    local directory result=0
    if [ -n "$run_dir" ]; then
        for directory in "$run_dir/head" "$run_dir/base"; do
            if [ -d "$directory" ]; then
                git -C "$repository" worktree remove --force "$directory" || result=1
            fi
        done
        if git -C "$repository" worktree list --porcelain | grep -F -- "worktree $run_dir/"; then
            echo 'Cleanup failed: temporary worktree remains registered.' >&2
            result=1
        fi
    fi
    return "$result"
}

cleanup() {
    local status=$?
    trap - EXIT INT TERM
    set +e
    set +m
    stop_process_groups
    stop_detached_services
    wait_for_services
    collect_logs_and_check_services || status=1
    remove_local_worktrees || status=1
    if [ "$status" -eq 0 ] && [ -f "$out/logs/summary.txt" ]; then cat "$out/logs/summary.txt"; fi
    exit "$status"
}

trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [ "$mode" = local ]; then
    while [ "$#" -gt 0 ]; do
        case "$1" in
            --head) [ "$#" -ge 2 ] || usage; head_commit=$2; shift 2 ;;
            -*) usage ;;
            *) [ -z "$base_commit" ] || usage; base_commit=$1; shift ;;
        esac
    done
    head_sha=$(git -C "$repository" rev-parse --verify "$head_commit^{commit}")
    if [ -z "$base_commit" ]; then
        base_sha=$(git -C "$repository" merge-base "$head_sha" origin/master)
    else
        base_sha=$(git -C "$repository" rev-parse --verify "$base_commit^{commit}")
    fi
    mkdir -p "$repository/temp/screenshots"
    run_dir=$(mktemp -d "$repository/temp/screenshots/run.XXXXXX")
    head=$run_dir/head
    base=$run_dir/base
    out=$run_dir/output
    mkdir -p "$out/logs"
    git -C "$repository" worktree add --quiet --detach "$head" "$head_sha"
    git -C "$repository" worktree add --quiet --detach "$base" "$base_sha"
elif [ "$mode" = run ]; then
    while [ "$#" -gt 0 ]; do
        [ "$#" -ge 2 ] || usage
        case "$1" in
            --head) head=$2 ;;
            --base) base=$2 ;;
            --out) out=$2 ;;
            *) usage ;;
        esac
        shift 2
    done
    [ -n "$head" ] && [ -n "$out" ] || usage
    head=$(cd "$head" && pwd)
    [ -z "$base" ] || base=$(cd "$base" && pwd)
    mkdir -p "$out/logs"
    out=$(cd "$out" && pwd)
    head_sha=$(git -C "$head" rev-parse HEAD)
    base_sha=$head_sha
    if [ -n "$base" ]; then base_sha=$(git -C "$base" rev-parse HEAD); else base_reason=not-provided; fi
else
    usage
fi

command -v flox >/dev/null || { echo 'Flox is required.' >&2; exit 1; }
# Node of the tools comes from the Flox environment of the checkout that holds this script.
tools_node=(flox activate -d "$repository" -- node)

prepare() {
    local directory=$1 label=$2
    failure_reason=
    if [ ! -f "$directory/.flox/env/manifest.toml" ] || [ ! -f "$directory/scripts/userdb_dev_setup.php" ]; then
        failure_reason=no-flox-setup
        return 1
    fi
    # run mode is for disposable CI checkouts too; never attach to an existing database or web.
    if [ -e "$directory/.flox/cache/mariadb/mariadb.pid" ]; then
        echo "Refusing an existing database: $directory" >&2
        failure_reason=setup-failed
        return 1
    fi
    launch "$directory" "$out/logs/$label-composer.log" \
        flox activate -- composer install --no-interaction --prefer-dist --no-progress
    if ! wait_job "$launched_pid"; then failure_reason=setup-failed; return 1; fi
    owned_labels="$owned_labels $label"
    launch "$directory" "$out/logs/$label-mariadb-start.log" flox activate -- bash scripts/userdb_mariadb.sh
    launch "$directory" "$out/logs/$label-setup.log" \
        env USERDB_DEMO_VOLUME=0 flox activate -- php scripts/userdb_dev_setup.php
    if ! wait_job "$launched_pid"; then failure_reason=setup-failed; return 1; fi
}

start_labelled_web() {
    local directory=$1 label=$2
    if ! start_web "$directory" "$out/logs/$label-web.log" "$out/logs/$label-web.pid" flox activate --; then
        return 1
    fi
    echo "$label web: $web_origin"
}

echo 'Preparing head database...'
if ! prepare "$head" head; then echo "Head setup failed: $failure_reason. See $out/logs" >&2; exit 1; fi
if [ -n "$base" ]; then
    echo 'Preparing base database...'
    if ! prepare "$base" base; then base_reason=$failure_reason; echo "Base unavailable: $base_reason"; fi
fi
# Both databases have been seeded before either version is captured.
if ! start_labelled_web "$head" head; then echo "Head web failed. See $out/logs" >&2; exit 1; fi
head_origin=$web_origin
if [ -n "$base" ] && [ -z "$base_reason" ]; then
    if start_labelled_web "$base" base; then
        base_origin=$web_origin
    else
        base_reason=$failure_reason
        echo "Base unavailable: $base_reason"
    fi
fi
launch "$repository" "$out/logs/head-capture.log" "${tools_node[@]}" "$tools_dir/capture.mjs" "$head_origin" head "$out"
wait_job "$launched_pid"
if [ -z "$base_reason" ]; then
    launch "$repository" "$out/logs/base-capture.log" "${tools_node[@]}" "$tools_dir/capture.mjs" "$base_origin" base "$out"
    wait_job "$launched_pid"
fi
"${tools_node[@]}" "$tools_dir/compare.mjs" "$out" "$head_sha" "$base_sha" "${base_reason:-}" > "$out/logs/summary.txt"
"${tools_node[@]}" "$tools_dir/validate.mjs" "$out" "$head_sha"
