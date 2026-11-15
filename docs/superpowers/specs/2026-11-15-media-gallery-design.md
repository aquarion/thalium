# Media gallery (art & music) — design

Status: draft, awaiting approval
Repos touched: `aquarion/thalium` (app), `aquarion/autopelago` (nginx/Ansible/mounts)

## Intent and why

Thalium is a searchable library of RPG PDFs, organised as `System/tag/.../file.pdf`
(first directory = system, every further directory = tag). Add a second, separate
gallery for **art** (images) and **music** (audio), using the same
system-and-tags-are-directories convention, so that during a game the GM can
browse a system's art and play its music.

The use is **browsing and playing mid-game**, not finding things. No search.

## Goals

- Browse by system, then by tag (directory), for art and for music separately.
- Art: thumbnail grid with a lightbox for showing a piece at full size.
- Music: track list with an audio player that **keeps playing while the user
  navigates** the rest of the gallery.
- Fast listing; new files appear within an hour (or immediately via a manual scan).
- Files only reachable by logged-in users.

## Non-goals

- Search (of any kind), Elasticsearch indexing, per-file text extraction.
- Playlists, favourites, per-session state. "Session support" is a later feature;
  nothing here should make it hard (items have stable IDs) but nothing is built for it.
- Uploading or editing files; tag editing in the UI. The filesystem is edited
  outside the app.
- Video, or non-image/non-audio files (ignored by the scan).

## Anti-goals

- Playback that stops or glitches when the user clicks to another page.
- Media URLs that work without a login session.
- Coupling to the PDF index: the PDF gallery must be unaffected.

## Constraints

- **Separate media root**, not the PDF root: new env var `DOCKER_MEDIA_LIBRARY`
  (container path, e.g. `/mnt/media`) and a new `media` filesystem disk,
  alongside the existing `libris` and `thumbnails` disks.
- **Static files are served by nginx**, never streamed by Laravel. Pages emit
  `/_media/...` URLs. nginx gates them with `auth_request /_authcheck`, exactly
  as `/_libris/` does today (cookie session → `/auth/checkLogin`). nginx range
  requests give audio seeking for free.
- Existing quirk to not copy: `/_thumbnails/` currently has **no** `auth_request`.
  Media thumbnails must be gated (see Serving).
- `autopelago` CLAUDE.md applies to the infra change: follow the existing
  `thalium.conf.j2` pattern; use `ansible_facts[...]` form if any facts are used;
  save full playbook output to a file when running it.
- Stack: Laravel 13, PHP 8.4, Imagick already available (used for PDF thumbnails),
  Bootstrap 4-style layout (`layouts/app.blade.php`).

## Approaches considered

| | Approach | Verdict |
|---|---|---|
| A | Read the filesystem directly, no index | Chosen, combined with C |
| B | Index into Elasticsearch like the PDFs | Rejected: only gain is search, which is a non-goal; adds jobs and staleness |
| C | Scan into database tables | Chosen, combined with A |

**Chosen: A + C.** The filesystem is the source of truth. A scan command mirrors
it into a database table that caches what is expensive to compute or read
(dimensions, durations, ID3 tags, thumbnails). Pages read only from the table.
Trade-off accepted: a new file isn't visible until the next scan (hourly, or
manual).

## Design

### Data

Table `media_items` (one row per file):

- `id`, `path` (relative to media root, unique), `kind` (`art` | `music`)
- `system` (derived exactly as `ParserService` does: first path segment,
  extension stripped, `-`/`_` → space), `tags` (JSON array of the remaining
  directory segments), `title` (filename-derived, or ID3 title for music)
- `mtime`, `size`
- art: `width`, `height`, `thumbnail` (filename on the thumbnails disk)
- music: `artist`, `album`, `duration_seconds`
- timestamps; indexes on `(kind, system)` and `path`.

### Scan (`media:scan`)

- Walks the `media` disk. Classifies by mime/extension: images (jpg, png, webp,
  gif) → art; audio (mp3, flac, ogg, m4a, wav) → music; everything else and
  dotfiles ignored (same hidden-file rule as `LibrisService::addDocument`).
- Upserts rows whose `mtime` changed; deletes rows (and their thumbnails) whose
  file is gone. Reads image dimensions and generates thumbnails with Imagick;
  reads audio tags with a tag-reading library (builder's choice; getID3 is the
  obvious candidate).
- Runs inline (no queue needed at this scale), scheduled hourly via the app
  scheduler, and runnable by hand. Idempotent. Logs counts of added/updated/removed.
- A file that fails to parse still gets a row with filename-derived metadata
  rather than being dropped.

### Pages

- `/media` (nav entry "Media"): systems that have media, with counts of art and
  music.
- `/media/{system}`: two sections, **Art** and **Music**, each with a tag filter
  (tags list with counts, same look as the PDF `systemGrid` view).
- Art: thumbnail grid, click opens a lightbox with the full image (served from
  `/_media/`); next/previous within the current tag filter.
- Music: track list (title, artist, duration); click plays.
- All behind the existing `auth` middleware, like the PDF routes.

### Player

A sticky bottom audio bar in the layout (HTML5 `<audio>`, play/pause, seek,
volume, next/previous, a simple in-memory queue built from the clicked list).
Gallery navigation inside `/media` is done by fetching page fragments and
swapping content (history API), so the `<audio>` element is never torn down.
Navigating out of `/media` to the PDF side is a normal page load and ends
playback; that is acceptable. The queue is not persisted (no playlists).

### Serving and infra (`autopelago`)

- `thalium.conf.j2`: add `location /_media/ { alias <host media path>/; auth_request /_authcheck; }`
  and `location /_media_thumbs/` pointing at a media-thumbnail directory,
  also with `auth_request`. Also add the same block to `etc/thalium.nginx.conf`
  in `thalium`.
- Host vars (`laravel_apps.yml`): `DOCKER_MEDIA_LIBRARY: /mnt/media` and the bind
  mount from the host media directory; mirror in `docker-compose.yml` and
  `.env.example` (`HOST_MEDIA_LIBRARY` / `DOCKER_MEDIA_LIBRARY`).
- Thumbnails live under a dedicated directory (`storage/app/media-thumbnails`)
  so they can be gated, rather than in the ungated `thumbnails` disk.
- Adding nginx locations/mounts only; nothing is removed or renamed, so no
  cleanup tasks are needed.

### Testing

- Unit: path → system/tags derivation; classification by extension.
- Feature (PHPUnit, existing `tests/` layout): `media:scan` against a fixture
  directory (add / change / delete); `/media` pages require auth; listing and
  tag filtering.
- nginx gating is verified manually (unauthenticated request to `/_media/...`
  returns 401).

## Left to the builder

- Choice of audio-tag library and exact accepted extensions.
- Fragment-swapping implementation (small vanilla JS vs a tiny library), as long
  as playback survives in-gallery navigation.
- Visual styling, lightbox implementation, pagination of very large tags.
- Whether thumbnails are PNG or WebP.

## My calls (not yet confirmed with you)

- Hourly scan plus manual command.
- Separate media thumbnail directory with auth, rather than reusing `thumbnails`.
- Sticky bottom bar player; playback ends when leaving `/media`.
- Ignoring non-image/non-audio files silently.
