#!/bin/bash


role=${CONTAINER_ROLE:-app}
env=${APP_ENV:-production}

echo "Running schedule loop..."
while [ true ]
do
  php /var/www/artisan schedule:run --verbose --no-interaction &
  sleep 60
done