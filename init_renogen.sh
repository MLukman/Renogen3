#!/bin/bash

if [ -n "$BASE_PATH" ] && [ ! -d "public/$BASE_PATH" ]; then 
  ln -s . "public/$BASE_PATH"
fi

if [ -n "$TZ" ]; then
  ln -snf /usr/share/zoneinfo/$TZ /etc/localtime
  echo $TZ > /etc/timezone
  echo "date.timezone=$TZ" > /usr/local/etc/php/conf.d/timezone.ini
fi

if [ ! -n "$DATABASE_URL" ]; then
  export DATABASE_URL="mysql://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
fi

APP_ENV_ORI=${APP_ENV:-prod}
export APP_ENV=dev
bin/console make:migration
export APP_ENV=${APP_ENV_ORI}
bin/console doctrine:migrations:migrate --no-interaction || echo No migrations needed!

# Call special migration code
bin/console app:migrate --no-interaction

# Cleanup orphans \App\Entity\FileStore
bin/console doctrine:query:dql "DELETE FROM \App\Entity\FileStore fs WHERE NOT EXISTS (SELECT 1 FROM \App\Entity\FileLink fl WHERE fl.filestore = fs)"

chown -R www-data:www-data var
