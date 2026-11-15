# Media gallery (art & music) — design

Status: draft, awaiting approval
Repos touched: `aquarion/thalium` only. No `autopelago` change.

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
- Full-size media files that work without a login session (thumbnails are
  excepted, as for PDFs; see Constraints).
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
- Existing quirk, deliberately left alone: `/_thumbnails/` has **no**
  `auth_request`, so PDF thumbnails (and, with this design, media thumbnails)
  are reachable without a login by anyone who knows the URL. Gating it is a
  separate piece of work, tracked in its own issue; it will cover media for free.
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

### Parsers (built on `ParserService`)

`ParseImageService` and `ParseAudioService` extend the existing `ParserService`,
inheriting its constructor: existence check, system/tags/title derivation from
the path, mtime, size guard. This keeps one definition of the directory
convention shared with the PDF side (so a later reshuffle, e.g. splitting
`Maps` into its own system, only changes it in one place).

- Image parser: reads dimensions, overrides `generateDocThumbnail()` with a real
  Imagick thumbnail. Audio parser: reads ID3/duration. `parsePages()` returns
  nothing (no text).
- They are created by a **separate factory** used only by the media scan. They
  must **not** be added to `LibrisService::getParser()`, which decides what the
  PDF indexer sends to Elasticsearch; adding them there would put media in PDF
  search and the PDF gallery.
- Fix the "is a ...Mb PDF" log wording in the `ParserService` constructor, which
  would otherwise be wrong for media.

### Scanning: `scanFile` routes by file type, plus `libris:update_media`

The existing `ScanDirectory` -> `ScanFile` job -> `LibrisService::scanFile()`
pipeline already visits every file in the library, so it is where media gets
picked up. (There is no method called `scanDocument`; I have taken `scanFile`
as the one meant.)

- `scanFile()` classifies each file and does the right thing for it:
  PDF/text -> the existing Elasticsearch path, unchanged; **image or audio ->
  media ingestion** (media parser -> upsert a `media_items` row, skipped if the
  `mtime` hasn't changed); anything else -> today's "unsupported" warning.
  No more log noise from media files, and no separate walker to maintain.
- Media ingestion never touches Elasticsearch. `getParser()` stays as it is.
- Classification: images (jpg, png, webp, gif) -> art; audio (mp3, flac, ogg,
  m4a, wav) -> music.
- Skips dotfiles **and dot-directories** (notably `.thalium/`, which holds the
  per-system thumbnails), matching the hidden-file rule in
  `LibrisService::addDocument`. Skips files directly in the library root: they
  have no system directory.
- A file that fails to parse still gets a row with filename-derived metadata
  rather than being dropped.
- Image dimensions and thumbnails via Imagick; audio tags via a tag-reading
  library (builder's choice; getID3 is the obvious candidate).

**`libris:update_media`** (named in the `libris:` family, not `media:scan`)
mirrors `libris:update`'s options (`--foreground`, `--start=.`). It walks the
tree and dispatches `ScanFile` jobs for image/audio files only, skipping PDFs
and text, and does not call the Elasticsearch `setup()`. Afterwards it deletes
`media_items` rows (and their thumbnails) whose file no longer exists. It is
idempotent, logs counts of added/updated/removed, runs on the app scheduler
hourly, and can be run by hand. A full `libris:update` also ingests media,
because it goes through the same `scanFile()`; it just doesn't prune.

### Pages and navigation

Each system has three sections, reached by a **`[ Documents | Art | Music ]`
nav** shown at the top of every one of its pages:

- Documents: the existing PDF page, `/system/{system}` (both its grid and list
  views).
- Art: `/system/{system}/art`. Thumbnail grid, tag filter (tags with counts, same
  look as the PDF `systemGrid` view), click opens a lightbox with the full image
  (served from `/_libris/`) with next/previous within the current tag filter.
- Music: `/system/{system}/music`. Tag filter and a track list (title, artist,
  duration); click plays.
- The nav is one shared partial (e.g. `partials/system-tabs.blade.php`) included
  by the PDF views and the two new views, so the three always match. It shows a
  count on each tab, keeps all three tabs visible, and dims one with zero items.
  The current tab is highlighted. Switching tabs drops the `?tag=` filter, since
  tags differ per section.
- The existing `docsBySystem` controller action gains the art and music counts
  to feed the partial; nothing else about the PDF page changes.
- Routes are named alongside the existing `system.index`
  (`system.art`, `system.music`), behind the same `auth` middleware.
- There is no separate `/media` landing page and no extra navbar entry: the
  home page's system list is the way in. To keep media-only systems reachable,
  the home page's system list also includes systems that exist only in
  `media_items` (their Documents tab is simply empty).

### Player

A sticky bottom audio bar in the layout (HTML5 `<audio>`, play/pause, seek,
volume, next/previous, a simple in-memory queue built from the clicked list).
Navigation between a system's three tabs, and between tags within a tab, is done
by fetching the page and swapping its content (history API), so the `<audio>`
element is never torn down and music keeps playing while you flip to Documents
or Art. Leaving the system pages (home, search, another system) is a normal page
load and ends playback. PDFs already open in a new tab, so reading one does not
interrupt it. The queue is not persisted (no playlists).

### Serving and infra (`autopelago`)

- Media files themselves: **no infra change.** Pages link to
  `{LIBRARY_URL}path`, i.e. the existing gated `/_libris/` location.
- Thumbnails reuse the existing `thumbnails` disk and `/_thumbnails/` location,
  named `md5(path).png` like the PDF ones (media and PDF paths differ, so no
  collisions). No `autopelago` or nginx change at all.

### Interaction with the PDF indexer

Handled by the routing in `scanFile()` above: media files are no longer an
"unsupported file" for the PDF scan, and PDFs/text are untouched. The PDF index
and gallery are otherwise unchanged.

### Testing

- Unit: path → system/tags derivation; classification by extension.
- Feature (PHPUnit, existing `tests/` layout): `scanFile()` routing (PDF -> ES path,
  image/audio -> `media_items`, other -> unsupported) and `libris:update_media`
  against a fixture directory (add / change / delete / prune); `/media` pages require auth; listing and
  tag filtering.
- nginx gating is verified manually (unauthenticated request to `/_libris/...`
  returns 401).

## Left to the builder

- Choice of audio-tag library and exact accepted extensions.
- Fragment-swapping implementation (small vanilla JS vs a tiny library), as long
  as playback survives in-gallery navigation.
- Visual styling, lightbox implementation, pagination of very large tags.
- Whether thumbnails are PNG or WebP.

## My calls (not yet confirmed with you)

- `libris:update_media`, hourly via the scheduler plus manual runs; pruning
  happens only in that command.
- Reuse `/_thumbnails/` (ungated, as for PDFs) rather than a gated directory;
  gating thumbnails is a separate issue.
- `scanFile()` ingests media itself (rather than skipping it), so a full
  `libris:update` picks media up too.
- Image files that already sit in the PDF tree (maps, cover images) will start
  appearing as art. I think that's wanted; say if not.
- Sticky bottom bar player; playback ends when leaving `/media`.
- Ignoring non-image/non-audio files silently.
