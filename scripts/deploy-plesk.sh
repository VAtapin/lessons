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
    php artisan migrate --force --no-interaction
elif $migrate_database; then
    # No migration may proceed unless the private SQL + immutable media bundle succeeded.
    php artisan lessons:database-preflight
    php artisan lessons:backup
    php artisan migrate --force --no-interaction
fi
php artisan lessons:check
php artisan optimize
php artisan up
curl --fail --silent --show-error --output /dev/null https://lessons.atapin.de/up
printf 'Deployed commit: %s\n' "$(git rev-parse --short HEAD)"
