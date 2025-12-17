#!/bin/bash
set -e

: "${APP_NAME:=app}"
APP_DIR="/opt/${APP_NAME}"

# Run composer install
if [ -f "${APP_DIR}/composer.json" ]; then
    cd "${APP_DIR}"
    composer install
fi

# Php configuration
if [ -n "$PHP_CUSTOM_INI" ]; then
  echo "$PHP_CUSTOM_INI" > /usr/local/etc/php/conf.d/zzz-custom.ini
fi
unset PHP_CONF

# Run the command
echo "Starting ${APP_NAME}..."
exec "$@"