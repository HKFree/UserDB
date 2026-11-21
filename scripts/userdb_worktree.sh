#!/usr/bin/env bash
# Local work in a fresh checkout or git worktree, on top of the project's Flox environment:
#   scripts/userdb_worktree.sh setup   Composer dependencies and this worktree's database with demo data
#   scripts/userdb_worktree.sh web     local web on a free port, started once the setup has finished
# Both are safe to repeat. Orca runs them for every new worktree, see orca.yaml.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

require_flox() {
    if command -v flox >/dev/null 2>&1; then
        return
    fi
    cat >&2 <<'MESSAGE'
Flox is not installed, so this worktree has no PHP, Composer or MariaDB.
Install it as described in "Tests with Flox" in readme.md (https://flox.dev/docs/install-flox/), then run:
    scripts/userdb_worktree.sh setup
MESSAGE
    return 1
}

setup() {
    flox activate -- composer install --no-interaction --prefer-dist --no-progress
    flox activate -s -- php scripts/userdb_dev_setup.php
}

# The setup writes the encryption key as its last step.
is_set_up() {
    [ -f vendor/autoload.php ] && [ -f .flox/cache/encryption.key ]
}

web() {
    if ! is_set_up; then
        echo 'Waiting for "scripts/userdb_worktree.sh setup" to finish. Run it in another terminal if nothing runs it.'
        until is_set_up; do
            sleep 1
        done
    fi
    exec flox activate -s -- php -S 127.0.0.1:0 -t www scripts/userdb_dev_router.php
}

case "${1:-}" in
    setup)
        # Missing Flox is reported without failing: after a failed setup Orca does not start the agent,
        # and the agent can still work on documentation or guide the installation.
        require_flox || exit 0
        setup
        ;;
    web)
        require_flox
        web
        ;;
    *)
        echo "Usage: $0 setup|web" >&2
        exit 2
        ;;
esac
