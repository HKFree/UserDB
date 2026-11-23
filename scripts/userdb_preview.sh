#!/usr/bin/env bash
# Runs one pull request preview in its container, see "Pull request previews" in readme.md and Dockerfile.preview:
# MariaDB, a database built from the migrations and demo data of the commit, and the local web behind the sign-in form.
set -euo pipefail

CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.."

# The Nix store of the image belongs to root, so root activates the environment.
# MariaDB refuses to run as root, and the code of a pull request should not either.
if [ "$(id -u)" -eq 0 ]; then
    exec flox activate -- setpriv --reuid=userdb --regid=userdb --init-groups \
        env HOME=/home/userdb bash "$PWD/scripts/userdb_preview.sh"
fi
: "${FLOX_ENV_CACHE:?Start the preview as root, or inside the activated Flox environment}"

export PHP_CLI_SERVER_WORKERS=4
export USERDB_GOOGLE_MAPS_KEY="${USERDB_GOOGLE_MAPS_KEY:-testing-only}"
export USERDB_TESTING_LOGIN_STATE="$FLOX_ENV_CACHE/testing-login"
mkdir -p "$USERDB_TESTING_LOGIN_STATE"

stop() {
    trap - TERM INT
    # shellcheck disable=SC2046
    kill $(jobs -p) 2> /dev/null || true
    wait
    exit 0
}
trap stop TERM INT

# The setup writes the encryption key as its last step. Without the key an earlier start of this container
# was interrupted, and what it left of the database may be neither complete nor startable.
if [ ! -f "$FLOX_ENV_CACHE/encryption.key" ]; then
    rm -rf "$FLOX_ENV_CACHE/mariadb"
fi

bash scripts/userdb_mariadb.sh &
if ! php scripts/userdb_dev_setup.php; then
    tail -n 50 "$FLOX_ENV_CACHE/logs/mariadb.log" >&2 || true
    exit 1
fi
# PHP itself shows no errors, as in production: a warning it prints before the router starts would otherwise
# replace the answer of the sign-in form. The errors of the application are shown by Tracy.
php -d display_errors=0 -d display_startup_errors=0 -S 0.0.0.0:8080 -t www scripts/userdb_preview_router.php &

# The container ends as soon as MariaDB or the web does; Coolify starts it again.
wait -n || true
echo "MariaDB or the web has ended, stopping the preview." >&2
exit 1
