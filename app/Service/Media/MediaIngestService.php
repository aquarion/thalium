<?php

namespace App\Service\Media;

use App\Exceptions\LibrisNotFound;
use App\Exceptions\LibrisTooLarge;
use App\Models\MediaItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;

class MediaIngestService
{
    private const TEXT_LIMIT = 255;

    /**
     * An image/audio file inside a system directory, outside any hidden
     * (dot-prefixed) file or directory such as `.thalium/`.
     */
    public function shouldIngest(string $path): bool
    {
        if (MediaKind::fromPath($path) === null) {
            return false;
        }

        $segments = explode('/', $path);

        if (count($segments) < 2) {
            return false;
        }

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.')) {
                return false;
            }
        }

        return true;

    }// end shouldIngest()

    /**
     * Create or refresh the row for one file. Null when the file is not
     * ingestible or has gone; an unchanged mtime is a no-op unless $force.
     */
    public function ingest(string $path, bool $force = false): ?MediaItem
    {
        if (! $this->shouldIngest($path)) {
            return null;
        }

        $disk = Storage::disk('libris');

        try {
            if ($disk->missing($path)) {
                Log::info("[Media] $path has gone, skipping");

                return null;
            }

            $mtime = (int) $disk->lastModified($path);
            $existing = MediaItem::query()->where('path_hash', MediaItem::hashPath($path))->first();

            if (! $force && $existing && (int) $existing->mtime === $mtime) {
                return $existing;
            }

            $parser = MediaParserFactory::make($path);
            $size = $disk->size($path);
        } catch (LibrisNotFound|LibrisTooLarge|FilesystemException $e) {
            Log::warning("[Media] Skipping $path: ".$e->getMessage());

            return null;
        }

        $metadata = $parser->metadata();
        $kind = $parser->kind();

        $item = $existing ?? new MediaItem;
        $item->fill([
            'path' => $path,
            'kind' => $kind,
            'system' => $parser->system,
            'tags' => array_values($parser->tags),
            'title' => $this->clean($metadata['title'] ?? '') ?: $this->clean($parser->title),
            'mtime' => $mtime,
            'size' => $size,
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
            'artist' => $this->clean($metadata['artist'] ?? null),
            'album' => $this->clean($metadata['album'] ?? null),
            'track_number' => $metadata['track_number'] ?? null,
            'duration_seconds' => $metadata['duration_seconds'] ?? null,
            'thumbnail' => $kind === MediaKind::Art ? $this->makeThumbnail($parser, $path) : null,
        ]);
        $item->save();

        return $item;

    }// end ingest()

    /**
     * Every ingestible path under $root.
     *
     * @return \Generator<int,string>
     */
    public function paths(string $root = '.'): \Generator
    {
        foreach (Storage::disk('libris')->allFiles($root) as $path) {
            if ($this->shouldIngest($path)) {
                yield $path;
            }
        }

    }// end paths()

    /**
     * Ingestible paths that have no row yet or whose mtime has moved on, so the
     * hourly run doesn't queue a job for every unchanged file.
     *
     * @return \Generator<int,string>
     */
    public function changedPaths(string $root = '.'): \Generator
    {
        $known = MediaItem::query()->pluck('mtime', 'path_hash');
        $disk = Storage::disk('libris');

        foreach ($this->paths($root) as $path) {
            try {
                $mtime = (int) $disk->lastModified($path);
            } catch (FilesystemException $e) {
                continue;
            }

            $recorded = $known[MediaItem::hashPath($path)] ?? null;

            if ($recorded === null || (int) $recorded !== $mtime) {
                yield $path;
            }
        }

    }// end changedPaths()

    /**
     * Delete rows (and thumbnails) whose file no longer exists. Returns null,
     * deleting nothing, when the library root has no directories at all: that is
     * an unmounted or emptied library, not one that lost all its media, and
     * pruning would empty the gallery until the next run.
     */
    public function prune(): ?int
    {
        if (Storage::disk('libris')->directories('.') === []) {
            Log::warning('[Media] The library has no directories; skipping prune');

            return null;
        }

        $removed = 0;

        MediaItem::query()->chunkById(200, function ($items) use (&$removed) {
            foreach ($items as $item) {
                if (Storage::disk('libris')->missing($item->path)) {
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

    private function makeThumbnail(ParseImageService $parser, string $path): ?string
    {
        try {
            $name = md5($path).'.png';

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
