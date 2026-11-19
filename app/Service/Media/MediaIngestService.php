<?php

namespace App\Service\Media;

use App\Exceptions\LibrisNotFound;
use App\Exceptions\LibrisTooLarge;
use App\Models\MediaItem;
use App\Service\ParserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;

class MediaIngestService
{
    private const TEXT_LIMIT = 255;

    /**
     * Whether a path is media in this source: an image/audio file inside a system
     * directory, or a map (an image or PDF in a Maps folder, or anywhere in the maps
     * tree), outside any hidden (dot-prefixed) file or directory such as `.thalium/`.
     */
    public function shouldIngest(string $path, MediaSource $source = MediaSource::Library): bool
    {
        return MediaClassifier::classify($source, $path) !== null;

    }// end shouldIngest()

    /**
     * Create or refresh the row for one file. Null when the file is not
     * ingestible or has gone; an unchanged mtime is a no-op unless $force or the
     * row's kind no longer matches where the file sits (a file moved into a Maps
     * folder keeps its mtime).
     */
    public function ingest(string $path, bool $force = false, MediaSource $source = MediaSource::Library): ?MediaItem
    {
        $classification = MediaClassifier::classify($source, $path);

        if ($classification === null) {
            return null;
        }

        $disk = Storage::disk($source->disk());

        try {
            if ($disk->missing($path)) {
                Log::info("[Media] $path has gone, skipping");

                return null;
            }

            $mtime = (int) $disk->lastModified($path);
            $existing = MediaItem::query()->where('path_hash', MediaItem::hashPath($path, $source))->first();

            if (! $force && $existing && (int) $existing->mtime === $mtime && $existing->kind === $classification->kind) {
                return $existing;
            }

            $parser = MediaParserFactory::make($path, $source);
            $size = $disk->size($path);
        } catch (LibrisNotFound|LibrisTooLarge|FilesystemException $e) {
            Log::warning("[Media] Skipping $path: ".$e->getMessage());

            return null;
        }

        $metadata = $parser->metadata();
        $kind = $classification->kind;

        $item = $existing ?? new MediaItem;
        $item->fill([
            'path' => $path,
            'source' => $source,
            'kind' => $kind,
            'system' => $classification->system,
            'collection' => $classification->collection,
            'tags' => array_values($classification->tags),
            'title' => $this->clean($metadata['title'] ?? '') ?: $this->clean($parser->title),
            'mtime' => $mtime,
            'size' => $size,
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
            'artist' => $this->clean($metadata['artist'] ?? null),
            'album' => $this->clean($metadata['album'] ?? null),
            'track_number' => $metadata['track_number'] ?? null,
            'duration_seconds' => $metadata['duration_seconds'] ?? null,
            'thumbnail' => $kind === MediaKind::Music ? null : $this->makeThumbnail($parser, $path, $source),
        ]);
        $item->save();

        return $item;

    }// end ingest()

    /**
     * Every ingestible path under $root in the source.
     *
     * @return \Generator<int,string>
     */
    public function paths(string $root = '.', MediaSource $source = MediaSource::Library): \Generator
    {
        foreach (Storage::disk($source->disk())->allFiles($root) as $path) {
            if ($this->shouldIngest($path, $source)) {
                yield $path;
            }
        }

    }// end paths()

    /**
     * Ingestible paths that have no row yet, whose mtime has moved on, or whose row
     * has the wrong kind for where the file sits, so the hourly run doesn't queue a
     * job for every unchanged file.
     *
     * @return \Generator<int,string>
     */
    public function changedPaths(string $root = '.', MediaSource $source = MediaSource::Library): \Generator
    {
        $known = MediaItem::query()->where('source', $source->value)->get(['path_hash', 'mtime', 'kind'])->keyBy('path_hash');
        $disk = Storage::disk($source->disk());

        foreach ($this->paths($root, $source) as $path) {
            try {
                $mtime = (int) $disk->lastModified($path);
            } catch (FilesystemException $e) {
                continue;
            }

            $row = $known[MediaItem::hashPath($path, $source)] ?? null;

            if ($row === null
                || (int) $row->mtime !== $mtime
                || $row->kind !== MediaClassifier::classify($source, $path)->kind
            ) {
                yield $path;
            }
        }

    }// end changedPaths()

    /**
     * Delete the source's rows (and thumbnails) whose file no longer exists. Returns
     * null, deleting nothing, when the source's root has no directories at all: that
     * is an unmounted or emptied library, not one that lost all its media, and
     * pruning would empty the gallery until the next run.
     */
    public function prune(MediaSource $source = MediaSource::Library): ?int
    {
        $disk = Storage::disk($source->disk());

        if ($disk->directories('.') === []) {
            Log::warning("[Media] The {$source->value} library has no directories; skipping prune");

            return null;
        }

        $removed = 0;

        MediaItem::query()->where('source', $source->value)->chunkById(200, function ($items) use (&$removed, $disk) {
            foreach ($items as $item) {
                if ($disk->missing($item->path)) {
                    if ($item->thumbnail) {
                        Storage::disk('thumbnails')->delete($item->thumbnail);
                    }
                    $item->delete();
                    $removed++;
                }
            }
        });

        return $removed;

    }// end prune()

    /**
     * Tag text goes into VARCHAR(255) columns: MySQL in strict mode rejects
     * longer text and invalid UTF-8, which would abort the whole update.
     */
    private function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return mb_substr(str_replace("\0", '', mb_scrub($text, 'UTF-8')), 0, self::TEXT_LIMIT);

    }// end clean()

    private function makeThumbnail(ParserService $parser, string $path, MediaSource $source): ?string
    {
        try {
            // Library names are the bare path hash (shared with the PDF thumbnails); the
            // maps tree is hashed apart so equal relative paths don't share a file.
            $name = md5($source === MediaSource::Library ? $path : $source->value.':'.$path).'.png';

            // put() returns false rather than throwing unless the disk sets `throw`.
            if (! Storage::disk('thumbnails')->put($name, $parser->generateDocThumbnail()->getImageBlob())) {
                Log::warning("[Media] Could not write the thumbnail for $path");

                return null;
            }

            return $name;
        } catch (\Throwable $e) {
            Log::warning("[Media] No thumbnail for $path: ".$e->getMessage());

            return null;
        }

    }// end makeThumbnail()

}// end class
