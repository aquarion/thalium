#!/bin/bash
git pull
docker compose build base
docker compose build
docker compose up -d
composer install
./artisan optimize:clear
./dartisan migrate
npm install
npm run build
