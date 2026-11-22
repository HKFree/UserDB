#!/usr/bin/env bash
# Runs the test suite with the PCOV driver and writes a line coverage report.
# The report format follows the file extension: .html for reading, .xml (Clover) for tools.
#
#   flox activate -s -- scripts/userdb_test_coverage.sh [report]
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
report="${1:-$root/temp/coverage/coverage.html}"
extension="${FLOX_ENV:?Run this script inside the Flox environment.}/lib/php/extensions/pcov.so"

case "$report" in
    *.html | *.xml) ;;
    *)
        echo "The report path must end in .html or .xml: $report" >&2
        exit 2
        ;;
esac
# A relative path belongs to the caller's directory, not to the repository root used below.
case "$report" in
    /*) ;;
    *) report="$PWD/$report" ;;
esac

mkdir -p "$(dirname "$report")"
cd "$root"
exec vendor/bin/tester -C \
    -d "extension=$extension" \
    --coverage "$report" \
    --coverage-src app \
    --coverage-src pdfGenerator/www \
    --coverage-src www/digisign-webhook \
    tests
