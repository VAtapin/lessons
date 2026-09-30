#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH"
composer_phar="/opt/psa/var/modules/composer/composer.phar"

php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 5 ? 0 : 1);'
node -e 'if (process.versions.node.split(".")[0] !== "22") process.exit(1)'
test -f "$composer_phar"
test -f .env || { echo "Create the private production .env before deployment." >&2; exit 1; }
test "$(git branch --show-current)" = main || { echo "Expected branch main." >&2; exit 1; }
test -z "$(git status --porcelain)" || { echo "Working tree must be clean before deployment." >&2; exit 1; }

git pull --ff-only
php "$composer_phar" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php "$composer_phar" check-platform-reqs --no-dev
npm ci --ignore-scripts
npm run typecheck
npm run build
php artisan optimize
curl --fail --silent --show-error --output /dev/null https://lessons.atapin.de/up
printf 'Deployed commit: %s\n' "$(git rev-parse --short HEAD)"
