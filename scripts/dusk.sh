#!/usr/bin/env bash
set -e

cd "$(dirname "$0")/.."

SERVE_PID=""

cleanup() {
    if [ -n "$SERVE_PID" ]; then
        kill "$SERVE_PID" 2>/dev/null || true
    fi
    if [ -f .env.backup.dusk ]; then
        mv .env.backup.dusk .env
    fi
}
trap cleanup EXIT

cp .env .env.backup.dusk
cp .env.dusk.local .env

php artisan migrate:fresh --force

php artisan serve --host=127.0.0.1 --port=8000 > /dev/null 2>&1 &
SERVE_PID=$!

for i in $(seq 1 20); do
    if curl -s -o /dev/null http://127.0.0.1:8000/login; then
        break
    fi
    sleep 0.5
done

php artisan dusk "$@"
