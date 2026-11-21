#!/usr/bin/env bash
# Runs the instances of HKFree/UserDB on the shared testing server: one for master, one per pull request.
#
# Installed on the server as /usr/local/bin/userdb-testing-deploy (owned by root) and run by sshd as the
# forced command of the deploy key, so the request arrives in SSH_ORIGINAL_COMMAND:
#   deploy master <commit>
#   deploy pr-<number> <commit>
#   remove pr-<number>
# An instance is a git worktree that runs the same way as on a developer machine: Flox, config.flox.neon,
# demo data, mail captured to files. Every deployment rebuilds the database of its instance.
set -euo pipefail
export LC_ALL=C

upstream="https://github.com/HKFree/UserDB.git"
base=/srv/userdb-testing
repository="$base/repository.git"
max_pr_instances=6

request="${SSH_ORIGINAL_COMMAND:-$*}"
if [[ "$request" =~ ^deploy\ (master|pr-[1-9][0-9]{0,3})\ ([0-9a-f]{40})$ ]]; then
    action=deploy
    instance="${BASH_REMATCH[1]}"
    commit="${BASH_REMATCH[2]}"
elif [[ "$request" =~ ^remove\ (pr-[1-9][0-9]{0,3})$ ]]; then
    action=remove
    instance="${BASH_REMATCH[1]}"
else
    echo "Usage: userdb-testing-deploy deploy master|pr-<number> <full commit SHA>" >&2
    echo "       userdb-testing-deploy remove pr-<number>" >&2
    exit 2
fi
if [ "$(id -u)" -eq 0 ]; then
    echo "Run as the deploy account, not as root: the deployed code would run as root." >&2
    exit 2
fi

if ! flox=$(command -v flox); then
    echo "Flox is not installed for the deploy account." >&2
    exit 1
fi
units="$HOME/.config/systemd/user"
XDG_RUNTIME_DIR="/run/user/$(id -u)"
export XDG_RUNTIME_DIR

mkdir -p "$base/instances" "$base/deployed" "$units"
exec 9> "$base/deploy.lock"
if ! flock --timeout 600 9; then
    echo "Other deployments have held the lock for 10 minutes." >&2
    exit 1
fi

# The reverse proxy maps the host name of an instance to this port.
web_port() {
    if [ "$1" = master ]; then
        echo 10000
    else
        echo $((10000 + ${1#pr-}))
    fi
}

# Only a commit of an upstream branch may run here: GitHub also serves commits of pull requests from forks.
fetch_commit() {
    if [ ! -d "$repository" ]; then
        git init --quiet --bare "$repository"
    fi
    git -C "$repository" fetch --quiet --prune --no-tags "$upstream" '+refs/heads/*:refs/remotes/origin/*'
    if [ -z "$(git -C "$repository" branch --remotes --contains "$commit" 2> /dev/null)" ]; then
        echo "Commit $commit is not on any branch of $upstream" >&2
        exit 1
    fi
    if ! git -C "$repository" cat-file -e "$commit:scripts/userdb_dev_setup.php" 2> /dev/null; then
        echo "Commit $commit is older than the Flox setup of the local web; update the branch from master." >&2
        exit 1
    fi
}

write_units() {
    local name="$1" checkout="$base/instances/$1"
    cat > "$units/userdb-testing-mariadb-$name.service" <<UNIT
[Unit]
Description=UserDB testing $name: MariaDB

[Service]
WorkingDirectory=$checkout
ExecStart=$flox activate -- bash scripts/userdb_mariadb.sh
Restart=on-failure

[Install]
WantedBy=default.target
UNIT
    cat > "$units/userdb-testing-web-$name.service" <<UNIT
[Unit]
Description=UserDB testing $name: web
Wants=userdb-testing-mariadb-$name.service
After=userdb-testing-mariadb-$name.service

[Service]
WorkingDirectory=$checkout
Environment=USERDB_GOOGLE_MAPS_KEY=testing-only
Environment=PHP_CLI_SERVER_WORKERS=4
ExecStart=$flox activate -- php -S 127.0.0.1:$(web_port "$name") -t www scripts/userdb_dev_router.php
Restart=on-failure

[Install]
WantedBy=default.target
UNIT
    systemctl --user daemon-reload
    systemctl --user enable --quiet "userdb-testing-mariadb-$name.service" "userdb-testing-web-$name.service"
}

stop_instance() {
    systemctl --user stop "userdb-testing-web-$1.service" "userdb-testing-mariadb-$1.service"
}

remove_instance() {
    local name="$1" checkout="$base/instances/$1"
    rm -f "$base/deployed/$name"
    systemctl --user disable --now --quiet \
        "userdb-testing-web-$name.service" "userdb-testing-mariadb-$name.service" 2> /dev/null || true
    rm -f "$units/userdb-testing-web-$name.service" "$units/userdb-testing-mariadb-$name.service"
    systemctl --user daemon-reload
    # An interrupted removal can leave a directory that git no longer knows, or the other way round.
    rm -rf "$checkout"
    if [ -d "$repository" ]; then
        git -C "$repository" worktree prune
    fi
}

# A failed deployment must not keep running outside the limit or keep its certificate.
abandon_instance() {
    echo "Deployment of $instance failed, its services are stopped." >&2
    rm -f "$base/deployed/$instance"
    stop_instance "$instance" || true
}

# The server has memory for a few instances: a new pull request replaces the one deployed longest ago.
make_room() {
    local oldest
    while [ "$(find "$base/deployed" -name 'pr-*' | wc -l)" -ge "$max_pr_instances" ]; do
        oldest=$(find "$base/deployed" -name 'pr-*' -printf '%T@ %f\n' | sort -n | head -n 1 | cut -d' ' -f2)
        echo "Removing $oldest to make room for $instance."
        remove_instance "$oldest"
    done
}

checkout_commit() {
    local checkout="$1"
    if git -C "$checkout" rev-parse --is-inside-work-tree > /dev/null 2>&1; then
        git -C "$checkout" checkout --quiet --force --detach "$commit"
        git -C "$checkout" clean --quiet -fd
    else
        rm -rf "$checkout"
        git -C "$repository" worktree prune
        git -C "$repository" worktree add --quiet --detach "$checkout" "$commit"
    fi
    # The database is rebuilt from scratch, so a commit with another MariaDB version or other accounts starts too.
    rm -rf "$checkout/.flox/cache/mariadb" "$checkout/temp/flox"
}

wait_for_web() {
    local url="http://127.0.0.1:$1/"
    for _ in $(seq 30); do
        if curl --silent --fail --max-time 5 --output /dev/null "$url"; then
            return
        fi
        sleep 1
    done
    echo "The web does not answer on $url, see: journalctl --user -u userdb-testing-web-$instance" >&2
    exit 1
}

deploy_instance() {
    local checkout="$base/instances/$instance"
    fetch_commit
    write_units "$instance"
    trap abandon_instance EXIT
    stop_instance "$instance"
    checkout_commit "$checkout"
    cd "$checkout"
    "$flox" activate -- composer install --no-interaction --prefer-dist --no-progress
    if [ "$instance" != master ] && [ ! -e "$base/deployed/$instance" ]; then
        make_room
    fi
    systemctl --user start "userdb-testing-mariadb-$instance.service"
    "$flox" activate -- php scripts/userdb_dev_setup.php
    systemctl --user start "userdb-testing-web-$instance.service"
    wait_for_web "$(web_port "$instance")"
    touch "$base/deployed/$instance"
    trap - EXIT
    echo "Deployed $commit as $instance"
}

if [ "$action" = deploy ]; then
    deploy_instance
else
    remove_instance "$instance"
    echo "Removed $instance"
fi
