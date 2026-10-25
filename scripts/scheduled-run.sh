#!/usr/bin/env bash
# The WSL clock until the Hetzner move (ADR-0009): Windows Task Scheduler starts this
# once a day. It runs trends:daily, then trends:weekly for every finished week not yet
# scored, so a Monday the PC was off is picked up on the next day it is on.
set -uo pipefail

cd "$(dirname "$0")/.."
. "$HOME/.config/php-env.sh"

mkdir -p storage/logs storage/app/scheduled
log="storage/logs/scheduled-$(date +%F).log"
exec >>"$log" 2>&1

exec 9>storage/app/scheduled/run.lock
flock -n 9 || { echo "$(date -Is) another scheduled run is still going, skipping"; exit 0; }

# Runs an artisan command; fails when it exits non-zero or skipped because another copy holds its lock.
run() {
    echo "$(date -Is) $*"
    local out status
    out=$(php artisan "$@" 2>&1)
    status=$?
    printf '%s\n' "$out"
    echo "$(date -Is) $1 exited $status"
    [ "$status" -eq 0 ] && ! grep -q "is already running\." <<<"$out"
}

# Weeks are UTC weeks starting Monday, as trends:weekly counts them.
monday() { date -u -d "$1 -$(( $(date -u -d "$1" +%u) - 1 )) days" +%F; }

run trends:daily || exit 1

last=$(date -u -d "$(monday "$(date -u +%F)") -7 days" +%F)
latest_done=$(ls storage/app/scheduled | sed -n 's/^weekly-\(.*\)\.done$/\1/p' | sort | tail -1)
week=${latest_done:+$(date -u -d "$latest_done +7 days" +%F)}
week=${week:-$last}

while [[ "$week" < "$last" || "$week" == "$last" ]]; do
    run trends:weekly --week="$week" || exit 1
    touch "storage/app/scheduled/weekly-$week.done"
    week=$(date -u -d "$week +7 days" +%F)
done
