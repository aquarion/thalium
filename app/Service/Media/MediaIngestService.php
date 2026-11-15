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
            'title' => $metadata['title'] ?? $parser->title,
            'mtime' => $mtime,
            'size' => $size,
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
            'artist' => $metadata['artist'] ?? null,
            'album' => $metadata['album'] ?? null,
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
     * Delete rows (and thumbnails) whose file no longer exists.
     */
    public function prune(): int
    {
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

    private function makeThumbnail(ParseImageService $parser, string $path): ?string
    {
        try {
            $name = md5($path).'.png';
            Storage::disk('thumbnails')->put($name, $parser->generateDocThumbnail()->getImageBlob());

            return $name;
        } catch (\Throwable $e) {
            Log::warning("[Media] No thumbnail for $path: ".$e->getMessage());

            return null;
        }

    }// end makeThumbnail()

}// end class
