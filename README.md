# Thalium

Thalium is a thing for making my RPG PDFs searchable.

# Making it go

## Docker

In theory, `docker-compose up` should just work.

Then ` docker-compose exec app composer install` should then install the things.

Then `docker-compose exec app artisan migrate` will put the database things in place

and `docker-compose exec app artisan migrate`

# Media gallery (art & music)

Images and audio files in the library sit beside the PDFs, in the same
`System/tag/.../file` layout: the first directory is the system, every
directory below it is a tag. Each system gets `Documents | Art | Music` tabs.

- Art: jpg, jpeg, png, webp, gif. Music: mp3, flac, ogg, m4a, wav.
- Files directly in the library root, and anything in a dot-directory
  (such as `.thalium/`), are ignored.
- `artisan libris:update_media` (hourly via the scheduler) adds new files,
  refreshes changed ones and removes rows for deleted ones. It takes
  `--foreground` and `--start=<dir>` like `libris:update`. A full
  `libris:update` also picks up new media but does not prune.
- Media is cached in the `media_items` table and never goes into Elasticsearch.
