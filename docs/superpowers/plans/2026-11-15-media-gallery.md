# Media Gallery (Art & Music) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add per-system Art and Music galleries beside the PDF gallery, with a `[ Documents | Art | Music ]` nav and a player that keeps playing across a system's tabs.

**Architecture:** Image/audio files in the existing library tree are parsed by new `ParserService` subclasses and cached in a `media_items` table by `LibrisService::scanFile()` (and a media-only `libris:update_media` command that also prunes). Pages read only the table; files and thumbnails are served by existing nginx locations. No Elasticsearch involvement, no infra change.

**Tech Stack:** Laravel 13, PHP 8.4, Imagick, `james-heinrich/getid3` (new), PHPUnit, Blade, vanilla JS via Vite.

**Spec:** `docs/superpowers/specs/2026-11-15-media-gallery-design.md`

**Running things:** this sandbox has PHP 8.3, no `vendor/`, no Imagick. Run commands inside the app container (`docker-compose exec app ...`) or rely on CI (`vendor/bin/phpunit`). Tests needing Imagick call `$this->markTestSkipped()` when `! extension_loaded('imagick')`. Follow the codebase's `}// end name()` closing-comment convention; run `vendor/bin/pint` and `vendor/bin/phpcs` before each commit.

## Global Constraints

- Media never touches Elasticsearch. `LibrisService::getParser()` is **not** changed and must not return media parsers.
- Same library tree, same `libris` disk (`DOCKER_PDF_LIBRARY`), same system/tags derivation as `ParserService` (first dir = system, further dirs = tags). No new root, env var, disk, mount, or `autopelago` change.
- Media files are linked via `Storage::disk('libris')->url($path)` (the gated `/_libris/`). Thumbnails live on the existing `thumbnails` disk named `md5(path).png`.
- Ignore dotfiles, files inside dot-directories (e.g. `.thalium/`), and files directly in the library root.
- Art extensions: jpg, jpeg, png, webp, gif. Music: mp3, flac, ogg, m4a, wav. Case-insensitive.
- Command is `libris:update_media` (options `--foreground`, `--start=.`), scheduled hourly in `routes/console.php`. Only it prunes.
- No search, no playlists, no per-user state. All routes behind `auth`.
- Player: sticky bottom bar; playback survives navigation between a system's three tabs and tag changes; leaving the system pages ends it.

## Review Focus

- Upper/mixed-case extensions (`.JPG`, `.Mp3`) are classified, not ignored (Task 1).
- Corrupt or zero-byte image/audio: row still created with filename-derived metadata, scan continues (Task 3).
- File deleted between dispatch and ingest: `ingest()` returns `null`, no exception (Task 3).
- Paths with spaces, `#`, `?`, unicode produce correctly encoded URLs (Task 1 `url()`, asserted in Task 6).
- A system with zero items of a kind (or a system that exists only as media) returns 200 with an empty state, not a 500 (Task 6).

## File Structure

- Create `database/migrations/2026_11_15_000000_create_media_items_table.php`
- Create `app/Models/MediaItem.php`; `app/Service/Media/MediaKind.php` (enum)
- Create `app/Service/Media/{MediaParserInterface,ParseImageService,ParseAudioService,MediaParserFactory,MediaIngestService}.php`
- Create `app/Console/Commands/LibrisUpdateMedia.php`; modify `routes/console.php`
- Modify `app/Libris/LibrisService.php` (`scanFile`), `app/Libris/LibrisInterface.php`, `app/Service/ParserService.php` (log wording)
- Create `app/Http/Controllers/MediaController.php`; modify `app/Http/Controllers/LibrisController.php`, `routes/web.php`
- Create views `resources/views/partials/system-tabs.blade.php`, `resources/views/media/{art,music}.blade.php`; modify `systemGrid`, `systemList`, `layouts/app.blade.php`
- Create `resources/js/media-player.js`; modify `resources/js/app.js`, `resources/sass/app.scss`
- Tests in `tests/Unit/` and `tests/Feature/` (named per task)

---

### Task 1: Schema, model, classification

**Files:** Create the migration, `MediaKind`, `MediaItem`; Test `tests/Unit/MediaKindTest.php`, `tests/Feature/MediaItemTest.php`

**Interfaces:**
- Produces: `enum MediaKind: string { case Art = 'art'; case Music = 'music'; public static function fromPath(string $path): ?self; }`
- Produces: `MediaItem` (fillable all columns; casts `tags` array, `kind` `MediaKind`): `url(): string`, `thumbnailUrl(): ?string`, `scopeOfKind($q, MediaKind $k)`, `static countsForSystem(string $system): array{art:int,music:int}`, `static systemsWithMedia(): Collection` (system => `['art'=>int,'music'=>int]`), `static tagCounts(MediaKind $k, string $system): array<string,int>` (sorted by tag).
- Table `media_items`: `id`, `path` unique, `kind`, `system`, `tags` json, `title`, `mtime` unsignedInteger, `size` unsignedBigInteger, `width`/`height` nullable int, `thumbnail` nullable string, `artist`/`album` nullable string, `duration_seconds` nullable unsignedInteger, timestamps; index `(kind, system)`.

- [ ] **Step 1: Write failing tests.** `MediaKindTest::test_classifies_case_insensitively`: `fromPath('A/b/Pic.JPG') === MediaKind::Art`, `'x/y.Mp3' === Music`, `'x/y.pdf'` and `'x/noext'` are `null`. `MediaItemTest` (uses `RefreshDatabase`): `test_url_encodes_each_segment` — path `'Pathfinder 2e/Maps/a #1?.png'` gives a `url()` ending `Pathfinder%202e/Maps/a%20%231%3F.png`; `test_counts_and_tag_counts` — three rows (2 art tagged `['Maps']`/`['Maps','City']`, 1 music) give `countsForSystem` `['art'=>2,'music'=>1]` and `tagCounts(Art, sys)` `['City'=>1,'Maps'=>2]`; `test_systems_with_media`.
- [ ] **Step 2:** Run `vendor/bin/phpunit --filter 'MediaKindTest|MediaItemTest'`. Expected: FAIL (classes missing).
- [ ] **Step 3: Implement** the migration, enum, and model above. `url()` = `rtrim(Storage::disk('libris')->url(''), '/')` + `'/'` + path with each segment `rawurlencode`d; `thumbnailUrl()` uses the `thumbnails` disk. `tagCounts` computes in PHP from `pluck('tags')`.
- [ ] **Step 4:** Re-run; Expected: PASS.
- [ ] **Step 5: Commit** `feat: media_items table, model and kind classification`

### Task 2: Media parsers on `ParserService`

**Files:** Create `MediaParserInterface`, `ParseImageService`, `ParseAudioService`, `MediaParserFactory`; Modify `composer.json`/`composer.lock` (`composer require james-heinrich/getid3`), `app/Service/ParserService.php` (change the `'Mb PDF'` log text to `'Mb file'`); Test `tests/Feature/MediaParserTest.php`

**Interfaces:**
- Consumes: `MediaKind::fromPath`.
- Produces: `interface MediaParserInterface { public function kind(): MediaKind; public function metadata(): array; }`. Image keys: `width`, `height`. Audio keys: `artist`, `album`, `duration_seconds`, `title` (only when the tag exists). Both parsers `extends ParserService implements MediaParserInterface`, `parsePages()` returns `[]`. `ParseImageService::generateDocThumbnail(): \Imagick` (longest side 400px). `MediaParserFactory::make(string $file): ?ParserService` returns the right parser or `null` for non-media; never throws for unreadable media (metadata just comes back `[]`).

- [ ] **Step 1: Write failing tests** using `Storage::fake('libris')`: `test_image_parser_reads_dimensions_and_derives_system_and_tags` (a generated 30x20 PNG at `Pathfinder 2e/Maps/City/a.png`: system `Pathfinder 2e`, tags `['Maps','City']`, metadata `width 30,height 20`; skip without Imagick); `test_audio_parser_reads_duration` (a WAV built with `pack()`, 1s of silence at 8000Hz: `duration_seconds === 1`); `test_corrupt_media_gives_empty_metadata` (zero-byte `.png` and `.mp3`: `metadata() === []`, no exception); `test_factory_returns_null_for_pdf`.
- [ ] **Step 2:** Run `--filter MediaParserTest`. Expected: FAIL.
- [ ] **Step 3: Implement.** Image: Imagick `pingImage` for size. Audio: `(new \getID3)->analyze(Storage::disk('libris')->path($file))`, reading `playtime_seconds` and the `tags` array; wrap in try/catch returning `[]`.
- [ ] **Step 4:** Re-run; Expected: PASS. Also run the existing suite to confirm `ParserService` still behaves.
- [ ] **Step 5: Commit** `feat: image and audio parsers built on ParserService`

### Task 3: `MediaIngestService`

**Files:** Create `app/Service/Media/MediaIngestService.php`; Test `tests/Feature/MediaIngestTest.php`

**Interfaces:**
- Consumes: `MediaParserFactory::make`, `MediaItem`, `MediaKind`.
- Produces: `shouldIngest(string $path): bool` (media kind, no hidden segment, contains a directory); `ingest(string $path): ?MediaItem` (null if not ingestible or the file is missing; no-op returning the existing row when `mtime` is unchanged; otherwise upserts by `path`; art rows get a thumbnail written to the `thumbnails` disk as `md5($path).png`); `paths(string $root = '.'): \Generator` (yields ingestible paths under `$root`); `prune(): int` (deletes rows whose file is gone, plus their thumbnail files; returns count).

- [ ] **Step 1: Write failing tests** (`Storage::fake` for `libris` and `thumbnails`, `RefreshDatabase`): `test_ingest_creates_row_with_derived_fields`; `test_ingest_skips_unchanged_mtime` (second call leaves `updated_at` and thumbnail untouched; changing the file updates the row); `test_corrupt_files_still_get_a_row` (zero-byte `.mp3`: row exists, `title` from filename, `duration_seconds` null); `test_ignores_hidden_and_root_files` (`.thalium/x.png`, `Sys/.hidden.png`, `loose.png` all return `null`, no rows); `test_ingest_returns_null_when_file_vanished`; `test_prune_removes_rows_and_thumbnails_for_deleted_files`; `test_paths_yields_only_ingestible_files`.
- [ ] **Step 2:** Run `--filter MediaIngestTest`. Expected: FAIL.
- [ ] **Step 3: Implement.** Use `Storage::disk('libris')->allFiles($root)` for `paths()`. A parser constructor throws `LibrisNotFound`/`LibrisTooLarge`; catch both inside `ingest()` (log, return null). Title: audio tag title if present, else parser title.
- [ ] **Step 4:** Re-run; Expected: PASS.
- [ ] **Step 5: Commit** `feat: media ingest and prune service`

### Task 4: `scanFile()` routes by type

**Files:** Modify `app/Libris/LibrisService.php` (`scanFile`); Test `tests/Feature/ScanFileRoutingTest.php`

**Interfaces:**
- Consumes: `MediaKind::fromPath`, `MediaIngestService::ingest`.
- Produces: `scanFile()` on an image/audio path calls `ingest()` and returns `0`, never reaching `addDocument`/Elasticsearch; all other behaviour unchanged.

- [ ] **Step 1: Write failing tests** (`Storage::fake('libris')`, `new LibrisService`): `test_image_is_ingested_not_indexed` — `scanFile('Sys/Maps/a.png')` returns `0`, a `media_items` row exists (any call into Elasticsearch would fail the test with a connection error); `test_hidden_media_is_quietly_ignored`; `test_unsupported_type_still_returns_zero` (`Sys/notes.xyz`).
- [ ] **Step 2:** Run `--filter ScanFileRoutingTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the early branch at the top of `scanFile()` via `app(MediaIngestService::class)`.
- [ ] **Step 4:** Re-run; Expected: PASS.
- [ ] **Step 5: Commit** `feat: scanFile ingests images and music into media_items`

### Task 5: `libris:update_media` and schedule

**Files:** Create `app/Console/Commands/LibrisUpdateMedia.php`; Modify `routes/console.php`; Test `tests/Feature/LibrisUpdateMediaTest.php`

**Interfaces:**
- Consumes: `MediaIngestService::{paths,ingest,prune}`, `ScanFile::dispatch`.
- Produces: signature `libris:update_media {--foreground : Run in foreground } {--start=. : The directory to start at}`. Background: dispatch one `ScanFile` per ingestible path, then `prune()`. Foreground: `ingest()` each path inline with counts of added/updated, then `prune()`. Never calls the Elasticsearch `setup()`. Prints counts.

- [ ] **Step 1: Write failing tests:** `test_background_dispatches_only_media_files` (`Queue::fake()`; files `Sys/a.png`, `Sys/b.pdf`, `.thalium/c.png`, `Sys/d.mp3`: asserts exactly two `ScanFile` pushed, matched via `(string) $job === 'ScanFile <Sys/a.png>'`); `test_foreground_ingests_and_prunes` (a stale row for a missing file is removed, a new row appears, output contains counts); `test_start_option_limits_the_walk`; `test_is_scheduled_hourly` (`schedule:list` output contains `libris:update_media`).
- [ ] **Step 2:** Run `--filter LibrisUpdateMediaTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the command and add `Schedule::command('libris:update_media')->hourly()->onOneServer()->withoutOverlapping();` to `routes/console.php` (the empty `Kernel::schedule()` is unused).
- [ ] **Step 4:** Re-run; Expected: PASS.
- [ ] **Step 5: Commit** `feat: libris:update_media command, hourly`

### Task 6: Pages, nav partial, routes

**Files:** Create `MediaController`, `partials/system-tabs.blade.php`, `media/art.blade.php`, `media/music.blade.php`; Modify `LibrisController` (`home`, `docsBySystem`), `LibrisInterface` (uncomment `getSystemThumbnail($system)`), `routes/web.php`, `systemGrid.blade.php`, `systemList.blade.php`; Test `tests/Feature/MediaPagesTest.php`

**Interfaces:**
- Consumes: `MediaItem` statics and `url()`/`thumbnailUrl()`.
- Produces: routes `system.art` = `GET /system/{system}/art`, `system.music` = `GET /system/{system}/music` (both `auth`), `MediaController::art(Request, string $system): View` / `music(...)`, query `?tag=` and `?page=` (60 per page for art, 200 for music). Partial `partials.system-tabs` takes `$system`, `$active` (`documents|art|music`), `$counts` (`['documents'=>int,'art'=>int,'music'=>int]`); every link carries `data-section-link`, a zero count adds class `disabled-tab` (still a link). Each page body is wrapped in `<div id="system-section">`. Music rows are `<button data-track-url data-track-title>`; art thumbnails link to the full-size URL with `data-lightbox`.
- `docsBySystem` passes `$counts`: documents = `$total` when there is no tag filter, else the total from `$libris->docsBySystem($system, 1, 1, 0)`.
- `home`: systems found only in `media_items` are appended to the ES list as `['system'=>…, 'count'=>0, 'thumbnail'=>$libris->getSystemThumbnail($system)]`.

- [ ] **Step 1: Write failing tests** (`RefreshDatabase`, `actingAs(User::factory()->create())`): `test_guests_are_redirected_to_login` for both routes; `test_art_page_lists_thumbnails_and_filters_by_tag`; `test_music_page_lists_tracks_with_encoded_urls` (a path with a space and `#`); `test_empty_system_renders_200_with_empty_state` (both pages, system with no rows); `test_tab_partial_marks_active_and_dims_empty` (render the partial directly: active class on the right tab, `disabled-tab` on a zero count, three hrefs match `route('system.index')`, `system.art`, `system.music`); `test_home_includes_media_only_systems` (`$this->mock(LibrisInterface::class)` with `systems()` returning `[]` and `getSystemThumbnail` returning a string; the home response contains the media-only system name).
- [ ] **Step 2:** Run `--filter MediaPagesTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the routes, controller (filter with `whereJsonContains('tags', $tag)`, `orderBy('title')`, `paginate()`), views, partial, and the `LibrisController` changes. Include the partial at the top of `systemGrid`/`systemList` and wrap their bodies in `#system-section`. Reuse the PDF page's breadcrumb and tag-chip markup.
- [ ] **Step 4:** Re-run; Expected: PASS. Then run the whole suite.
- [ ] **Step 5: Commit** `feat: Documents/Art/Music nav and art and music pages`

### Task 7: Player and in-page navigation

**Files:** Create `resources/js/media-player.js`; Modify `resources/js/app.js` (import it), `resources/sass/app.scss` (bar styles, bottom padding on `body` when the bar is present), `resources/views/layouts/app.blade.php`; Test `tests/Feature/MediaPlayerMarkupTest.php`; Modify `README.md`

**Interfaces:**
- Produces: in the layout, inside `@auth`, a persistent `<div id="media-player">` holding `<audio>` with prev/play-pause/next, a seek input, volume, and the current title, hidden until a track is chosen.
- `media-player.js` behaviour: (1) delegated click on `[data-track-url]` builds the queue from every `[data-track-url]` currently in `#system-section`, starts at the clicked one, and `ended` advances; (2) delegated click on `a[data-section-link]` (tabs and tag chips): `fetch` the URL, parse with `DOMParser`, replace `#system-section` and `document.title`, `history.pushState`; a `popstate` listener does the same swap; on any fetch failure fall back to `location.href = url`. (3) delegated click on `a[data-lightbox]` opens a lightbox (a Bootstrap modal is fine) showing the full-size image with previous/next across the `[data-lightbox]` links currently in `#system-section` (i.e. within the current tag filter and page); Escape closes. The `<audio>` element is never recreated; the queue is in memory only.

- [ ] **Step 1: Write failing test** `test_player_markup_only_for_authenticated_users`: an authed GET of `/system/X/music` contains `id="media-player"` and `data-section-link`; the login page does not contain `id="media-player"`.
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3: Implement** the layout block, JS, and styles. Build with `npm run build` (confirms Vite compiles).
- [ ] **Step 4:** Re-run the test (PASS), then verify by hand in the dev stack, because the repo has no JS test harness: start a track on `/system/<sys>/music`, click the Art tab, the Documents tab, and a tag chip. Expected: audio never stutters or restarts, the URL and highlighted tab update, Back/Forward restore the right tab, and following a link to the home page stops playback. Report what you observed.
- [ ] **Step 5:** Add a short "Media gallery" section to `README.md` (layout convention, extensions, `libris:update_media`, hourly schedule). **Commit** `feat: sticky audio player with in-page tab navigation`

---

## Self-review notes

- Spec coverage: data/table (T1), parsers on `ParserService` + log wording (T2), ignore rules/prune/thumbnails (T3), `scanFile` routing (T4), `libris:update_media` + hourly + prune-only-there (T5), pages/tabs/counts/media-only systems/no `/media` landing (T6), player behaviour (T7). `autopelago` is untouched by design.
- Hourly scheduling goes in `routes/console.php`, where the existing schedules live, not the unused `Kernel`.
- Open for the builder (per spec): tag-reading library details beyond getID3, lightbox implementation, thumbnail format (PNG here, to match existing).
