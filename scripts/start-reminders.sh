#!/bin/bash
set -euo pipefail
project_dir=$(cd -- "$(dirname -- "$0")/.." && pwd)
php_bin=${PHP_BIN:-$(command -v php)}
cron_bin=${SUPERCRONIC_BIN:-$(command -v supercronic)}
# PHP establece la zona horaria de la app. Se ejecuta cada diez minutos,
# por lo que no depende de la zona horaria ni de una visita del navegador.
mkdir -p "$project_dir/storage/logs"
cron_file="$project_dir/storage/logs/reminders.crontab"
printf '*/10 * * * * PHP_BIN=%q %q\n' "$php_bin" "$project_dir/scripts/run-reminders.sh" > "$cron_file"
"$cron_bin" -test "$cron_file"
exec "$cron_bin" "$cron_file"
