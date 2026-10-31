#!/bin/bash
# The server's update job for this website (a cron job runs it every minute). PHP here may not start
# programs, so when the billing app asks for an update (storage/app/code-update/request.json) this does it:
# git pull with fast-forward only (a file changed on the server stops it, nothing is overwritten), composer
# when composer.lock changed, then the caches. What happened goes to storage/app/code-update/result.json.
# Everything is inside main() so bash has read the whole script before git replaces it.

main() {
    local app q php before after status log started
    app="$(cd "$(dirname "$0")/.." && pwd)" || return
    q="$app/storage/app/code-update"
    mkdir -p "$q" && touch "$q/runner-seen"
    [ -f "$q/request.json" ] || return 0
    exec 9>"$q/lock"
    flock -n 9 || return 0
    mv "$q/request.json" "$q/running.json" || return

    php="${CODE_UPDATE_PHP:-/opt/alt/php85/usr/bin/php}"
    [ -x "$php" ] || php="$(command -v php)"
    started="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    log="$q/last.log"
    cd "$app" || return
    before="$(git rev-parse HEAD)"
    status=success
    {
        echo "\$ git pull --ff-only origin main"
        git pull --ff-only origin main 2>&1 || status=failed
        after="$(git rev-parse HEAD)"
        if [ "$status" = success ] && [ "$before" != "$after" ] && ! git diff --quiet "$before" "$after" -- composer.lock; then
            echo "\$ composer install --no-dev"
            # The scripts step needs proc_open (not allowed here); package:discover below does its work.
            "$php" "$(command -v composer)" install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts 2>&1 || status=failed
            "$php" artisan package:discover 2>&1 || status=failed
        fi
        if [ "$status" = success ]; then
            echo "\$ php artisan optimize"
            "$php" artisan optimize 2>&1 || status=failed
            chmod 600 bootstrap/cache/config.php 2>/dev/null
        fi
    } >"$log" 2>&1

    STATUS="$status" BEFORE="$before" AFTER="$(git rev-parse HEAD)" STARTED="$started" LOG="$log" REQ="$q/running.json" "$php" -r '
        $req = json_decode((string) @file_get_contents(getenv("REQ")), true) ?: [];
        echo json_encode([
            "status" => getenv("STATUS"),
            "from" => substr(getenv("BEFORE"), 0, 7),
            "to" => substr(getenv("AFTER"), 0, 7),
            "asked_at" => $req["at"] ?? null,
            "by" => $req["by"] ?? null,
            "started_at" => getenv("STARTED"),
            "finished_at" => gmdate("Y-m-d\TH:i:s\Z"),
            "log" => mb_substr((string) @file_get_contents(getenv("LOG")), -6000),
        ]);
    ' >"$q/result.json.tmp" && mv "$q/result.json.tmp" "$q/result.json"
    rm -f "$q/running.json"
}

main "$@"
exit
