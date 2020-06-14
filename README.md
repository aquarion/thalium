# Thalium

Thalium is a thing for making my RPG PDFs searchable.

# Making it go

## Docker

In theory, `docker-compose up` should just work.

Then ` docker-compose exec app composer install` should then install the things.

Then `docker-compose exec app artisan migrate` will put the database things in place

and `docker-compose exec app artisan migrate`