# Maps tab and Maps page — design

Status: draft, awaiting approval
Repos touched: `aquarion/thalium` (app) and `aquarion/autopelago` (mount + nginx).

## Intent and why

Maps (battle maps, city maps, building plans) are used differently from other art and
from rulebooks: a GM wants them together, mid-game. Today a map JPG shows up under Art
and a map PDF under Documents, mixed in with everything else. Give maps their own tab
per system, and a root Maps page that also covers the separate `RPG/Maps` library.

## Goals

- Any image or PDF inside a directory named `Maps` (case-insensitive, any depth) under
  a system appears in that system's **Maps** tab, and **not** in Art or Documents.
- A root page, **Maps**, shows every map: those from `Systems/.../Maps/...` and
  everything in the `RPG/Maps` tree that sits beside `Systems`.
- Browsing works as for Art: thumbnail grid, tag chips, lightbox for images; PDFs open
  in the existing viewer.

## Non-goals

- Search inside map PDFs (they leave Elasticsearch; accepted trade-off).
- Ingesting `psd`, `psb`, `tif`, `bmp`, `zip`, `db`, `lnk`, `sh`, `txt` found in the
  Maps tree. They are ignored, as unsupported files already are.
- Making a system appear on the home page solely because it has `RPG/Maps` content.

## Anti-goals

- Map PDFs still visible in Documents, counts, or search after the change.
- `BattleMats`, `Neutral Party maps`, `Loke BattleMats`, `tmr` showing up as systems.

## Constraints

- `libris` disk is rooted at `/home/library/RPG/Systems` (`/mnt/rpg`); `RPG/Maps` is not
  mounted or served today. nginx serves files from `/_libris/` behind `auth_request`.
- Media is cached in `media_items`, never in Elasticsearch; PDFs are in Elasticsearch.
- `autopelago/CLAUDE.md` applies to the infra change (follow `thalium.conf.j2`; save full
  playbook output to a file; no task is removed, so no cleanup task is needed).

## Approaches considered

| | Approach | Verdict |
|---|---|---|
| A | Maps are media: new `map` kind in `media_items`, map PDFs leave Elasticsearch | **Chosen** |
| B | Keep maps in Elasticsearch with an `is_map` flag, filter Documents | Rejected: index mapping change and reindex; Maps pages on a different data path from Art |
| C | Both stores | Rejected: same file in two places to keep in sync |

Trade-off accepted: map PDFs are no longer text-searchable.

## Design

### What counts as a map

- In the Systems library (`libris` disk): a jpg/jpeg/png/webp/gif or **pdf** with a
  directory segment equal (case-insensitively) to `Maps` anywhere above the file. The
  `Maps` segment is dropped from `tags`; other segments stay tags.
- In the `RPG/Maps` tree (new `maps` disk): every image or pdf, except files directly in
  the root and anything in a dot file/directory (as today).
- Outside Maps locations nothing changes: images are Art, PDFs are Documents.

### Data (`media_items`)

- `kind` gains `map`.
- New `source` (`library` | `maps`); a row is unique per (`source`, `path`), so
  `path_hash` is computed over both. The migration backfills existing rows as `library`.
- New nullable `collection`.
  - Systems library rows: `system` as today, `collection` null.
  - `RPG/Maps` rows: `system` null, `collection` = normalised first directory
    (`-`/`_` become spaces, as for systems), `tags` = the directories below it.
- Matching beside-tree maps to a system is done **at query time**, not stored: a
  system's Maps tab shows rows where `system = X` **or** (`source = maps` and
  `collection = X`). So `Maps/Blades in the Dark/...` appears in that system's tab, and
  `BattleMats` etc. appear only on the root page. No ordering or staleness issue at
  ingest. System-agnostic rows have no system, so `systemsWithMedia()` never lists them
  as systems.

### Ingest and Elasticsearch

- `MediaIngestService` learns the two roots and the map rule, and classifies per path
  (a file's kind now depends on where it is, not just its extension). PDF map
  thumbnails reuse the existing Imagick first-page code; image maps use
  `ParseImageService`.
- `LibrisService::scanFile()` / `addDocument()` skip PDFs that sit under a `Maps`
  segment (they go to media ingestion instead), and delete any Elasticsearch document
  already indexed for such a path.
- `changedPaths()` also yields paths whose recorded `kind`/`source` differs from the
  rule, so existing Art rows under `Maps` folders are reclassified even though their
  mtime is unchanged. `libris:update_media` walks both disks; prune checks each root
  separately and keeps its "root has no directories, so skip prune" guard per root.
- One-off: a first run purges the existing Elasticsearch documents for map PDFs.

### UI

- System tabs become `Documents | Art | Music | Maps` with counts, disabled when zero
  (existing `system-tabs` partial and `SystemSections`). Tag filter carries across, as now.
- `/system/{system}/maps` (`system.maps`) reuses the Art grid and lightbox view; a PDF
  card opens `document.iframe`-style viewing in a new tab.
- Root `/maps` (`maps.index`), linked from the main nav: all maps grouped by system,
  or by collection where there is no system, with tag chips and the same grid.
- Home page and Documents/Art counts exclude maps automatically.

### Infra (autopelago)

- Bind-mount `/home/library/RPG/Maps` to `/mnt/maps` for prod and staging
  (`host_vars/firth.water.gkhs.net/laravel_apps.yml`), set `MAPS_LIBRARY` /
  `MAPS_URL`, and add a gated `/_maps/` alias to `thalium.conf.j2`, mirroring
  `/_libris/`. Thalium gets a `maps` filesystem disk, and `compose.yaml` / README get a
  `HOST_MAPS_LIBRARY` for dev.

### Tests

Feature tests against a fixture tree: map classification in both roots (case, depth,
dot-dirs, root files), reclassification of existing rows, PDFs absent from the
Elasticsearch path and Documents counts, the tab and root page (auth, grouping,
collection vs system, counts), and prune across both roots.

## Calls I made (change any)

- Case-insensitive `Maps` match at any depth; the `Maps` segment is dropped from tags.
- System/collection matching at query time rather than stored.
- A system with only beside-tree maps does not get a home-page entry.
- Ignored file types as listed under Non-goals.

## Left to the builder

Exact migration and hash backfill mechanics, thumbnail code reuse for PDFs, the nav
placement, and CSS for the Maps tab.
