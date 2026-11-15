# Media gallery (art & music) — design

Status: draft, awaiting approval
Repos touched: `aquarion/thalium` (app), `aquarion/autopelago` (one nginx location for gated thumbnails)

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

- **Same library, same filesystem, same system directories as the PDFs.** Art
  and music sit beside the PDFs in `System/tag/.../file`. No new root, mount,
  env var or disk: the scan uses the existing `libris` disk
  (`DOCKER_PDF_LIBRARY`, `/mnt/rpg`). Only the *gallery* is separate.
- **Static files are served by nginx**, never streamed by Laravel. The existing
  `/_libris/` location already serves the whole library behind
  `auth_request /_authcheck` (cookie session → `/auth/checkLogin`), so media
  files are already gated and need no new nginx location. nginx range requests
  give audio seeking for free.
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

- `id`, `path` (relative to the library root, unique), `kind` (`art` | `music`)
- `system` (derived exactly as `ParserService` does: first path segment,
  extension stripped, `-`/`_` → space), `tags` (JSON array of the remaining
  directory segments), `title` (filename-derived, or ID3 title for music)
- `mtime`, `size`
- art: `width`, `height`, `thumbnail` (filename on the thumbnails disk)
- music: `artist`, `album`, `duration_seconds`
- timestamps; indexes on `(kind, system)` and `path`.

### Scan (`media:scan`)

- Walks the `libris` disk. Classifies by mime/extension: images (jpg, png, webp,
  gif) → art; audio (mp3, flac, ogg, m4a, wav) → music; everything else
  (including the PDFs, which the existing indexer owns) ignored.
- Skips dotfiles **and dot-directories** (notably `.thalium/`, which holds the
  per-system thumbnails), matching the hidden-file rule in
  `LibrisService::addDocument`.
- Skips files sitting directly in the library root: they have no system
  directory (the PDF parser would misread the filename as a system).
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

- Media files themselves: **no infra change.** Pages link to
  `{LIBRARY_URL}path`, i.e. the existing gated `/_libris/` location.
- Thumbnails live under a dedicated directory (`storage/app/media-thumbnails`)
  so they can be gated, rather than in the ungated `thumbnails` disk. That needs
  one new location, `location /_media_thumbs/ { alias ...; auth_request /_authcheck; }`,
  in `autopelago` (`thalium.conf.j2`, mirroring the `/_thumbnails/` alias path
  but with the auth line) and in `thalium` (`etc/thalium.nginx.conf`). This is
  the only `autopelago` change; it adds a location and removes or renames
  nothing, so no cleanup tasks are needed.

### Interaction with the PDF indexer

`ScanDirectory`/`ScanFile` walk the same tree and send every file to
`LibrisService::getParser`. Images and audio currently end as a logged
"No parser" `LibrisFileNotSupported` warning (caught in `scanFile`), so nothing
breaks, but every media file would add log noise on each PDF scan. The builder
should make the PDF scan skip image/audio files quietly (a small check in
`dispatchIndexFile`). The PDF index and gallery are otherwise unchanged.

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
- Separate media thumbnail directory with auth, rather than reusing `thumbnails`
  (the only infra change now that files share the `/_libris/` location).
- Quiet skip of image/audio files in the PDF scan to avoid log noise.
- Image files that already sit in the PDF tree (maps, cover images) will start
  appearing as art. I think that's wanted; say if not.
- Sticky bottom bar player; playback ends when leaving `/media`.
- Ignoring non-image/non-audio files silently.
