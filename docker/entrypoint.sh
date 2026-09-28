#!/bin/sh
set -eu

php bin/console doctrine:database:create --if-not-exists --no-interaction --env=dev
php bin/console doctrine:migrations:migrate --no-interaction --env=dev

php bin/console doctrine:database:create --if-not-exists --no-interaction --env=test
php bin/console doctrine:migrations:migrate --no-interaction --env=test

exec php -S 0.0.0.0:8000 -t public public/index.php