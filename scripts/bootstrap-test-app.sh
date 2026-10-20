#!/usr/bin/env bash
# Creates a fresh Laravel 12 app with Sanctum, copies api/ over it and runs the tests.
set -euo pipefail
REPO="$(cd "$(dirname "$0")/.." && pwd)"
APP="${1:-/tmp/payments-app}"
rm -rf "$APP"
composer create-project --no-interaction --prefer-dist laravel/laravel "$APP" "^12.0"
cd "$APP"
php artisan install:api --no-interaction
cp -r "$REPO/api/." .
# Give the User model Sanctum tokens.
grep -q HasApiTokens app/Models/User.php || sed -i \
  -e 's/use HasFactory, Notifiable;/use HasApiTokens, HasFactory, Notifiable;/' \
  -e 's/^use Illuminate\\Notifications\\Notifiable;/use Illuminate\\Notifications\\Notifiable;\nuse Laravel\\Sanctum\\HasApiTokens;/' \
  app/Models/User.php
php artisan migrate --force
php artisan test
