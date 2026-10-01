#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH"
composer_phar="/opt/psa/var/modules/composer/composer.phar"
initialize_database=false
migrate_database=false
if [[ "${1:-}" == "--initialize-database" && $# == 1 ]]; then
    initialize_database=true
elif [[ "${1:-}" == "--migrate" && $# == 1 ]]; then
    migrate_database=true
elif [[ $# != 0 ]]; then
    echo "Usage: bash scripts/deploy-plesk.sh [--initialize-database|--migrate]" >&2
    exit 1
fi

php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 5 ? 0 : 1);'
node -e 'if (process.versions.node.split(".")[0] !== "22") process.exit(1)'
test -f "$composer_phar"
test -f .env || { echo "Create the private production .env before deployment." >&2; exit 1; }
test "$(git branch --show-current)" = main || { echo "Expected branch main." >&2; exit 1; }
test -z "$(git status --porcelain)" || { echo "Working tree must be clean before deployment." >&2; exit 1; }

command -v flock >/dev/null || { echo "flock is required for safe deployment and background backups." >&2; exit 1; }
operations_lock=$(php scripts/operations-lock.php)
exec 9<>"$operations_lock"
flock --exclusive --nonblock 9 || { echo "Another backup or deployment is running; no deployment changes were started." >&2; exit 1; }

initially_down=false
if [[ -f storage/framework/down ]]; then initially_down=true; fi
deployment_started=false
schema_changes_started=false
deployment_completed=false
source scripts/deployment-recovery.sh

deployment_started=true
php artisan down --retry=30
git pull --ff-only
php "$composer_phar" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php "$composer_phar" check-platform-reqs --no-dev
npm ci --ignore-scripts
npm run typecheck
npm run build
php artisan optimize:clear
if $initialize_database; then
    # Only a metadata-verified empty schema may be initialized without a backup.
    php artisan lessons:database-preflight --empty
    schema_changes_started=true
    php artisan migrate --force --no-interaction
elif $migrate_database; then
    # No migration may proceed unless the private SQL + immutable media bundle succeeded.
    php artisan lessons:database-preflight
    php artisan lessons:backup
    schema_changes_started=true
    php artisan migrate --force --no-interaction
fi
php artisan lessons:check
php artisan optimize
php artisan queue:restart
php artisan up
deployment_completed=true
curl --fail --silent --show-error --output /dev/null https://lessons.atapin.de/up
printf 'Deployed commit: %s\n' "$(git rev-parse --short HEAD)"
