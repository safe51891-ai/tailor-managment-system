#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if [[ -n "${PHP_BIN:-}" ]]; then
    php_binary="$PHP_BIN"
elif [[ -x /opt/homebrew/opt/php@7.4/bin/php ]]; then
    php_binary=/opt/homebrew/opt/php@7.4/bin/php
elif [[ -x /usr/local/opt/php@7.4/bin/php ]]; then
    php_binary=/usr/local/opt/php@7.4/bin/php
else
    php_binary=php
fi

exec "$php_binary" artisan serve --host=127.0.0.1 --port="${PORT:-8000}"
