# Thalium

Thalium is a thing for making my RPG PDFs searchable.

# Making it go

## Local development (Laravel Sail)

```sh
cp .env.example .env            # sets Sail hostnames for mariadb/redis/elasticsearch/mailpit
composer install                # needs vendor/ so Sail can build its image
vendor/bin/sail build           # first run only (builds the PHP 8.4 + Java/PDFBox image)
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate
vendor/bin/sail artisan test
```

The stack is `compose.yaml`: app, MariaDB, Redis, Elasticsearch, Mailpit. The app
container also runs the Vite dev server (port 5173), installing `node_modules`
on first start. Set
`HOST_PDF_LIBRARY` in `.env` to mount a PDF library at `/mnt/rpg`. Production
and staging are deployed from the `autopelago` repo, not from this checkout.

# Media gallery (art & music)

Images and audio files in the library sit beside the PDFs, in the same
`System/tag/.../file` layout: the first directory is the system, every
directory below it is a tag. Each system gets `Documents | Art | Music` tabs.

- Art: jpg, jpeg, png, webp, gif. Music: mp3, flac, ogg, m4a, wav.
- Files directly in the library root, and anything in a dot-directory
  (such as `.thalium/`), are ignored.
- `artisan libris:update_media` (hourly via the scheduler) adds new files,
  refreshes changed ones and removes rows for deleted ones. It takes
  `--foreground` and `--start=<dir>` like `libris:update`, which does everything
  `update_media` does: it picks up new media and prunes deleted files too.
- Media is cached in the `media_items` table and never goes into Elasticsearch.

## Maps

Images and PDFs inside a directory named `Maps` (any case, below the system
directory) are maps: they show in the system's **Maps** tab instead of Art or
Documents. The `RPG/Maps` tree beside `Systems` is the second source; mount it
with `HOST_MAPS_LIBRARY` and set `MAPS_LIBRARY` (left unset, it is skipped).
