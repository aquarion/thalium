#!/bin/bash
git pull
chgrp -fR www-data storage bootstrap
docker compose build base
docker compose build
docker compose up -d
composer install
./artisan optimize:clear
./dartisan migrate
npm install
npm run build
