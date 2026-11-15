<?php

namespace App\Models;

use App\Service\Media\MediaKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MediaItem extends Model
{
    protected $fillable = [
        'path',
        'path_hash',
        'kind',
        'system',
        'tags',
        'title',
        'mtime',
        'size',
        'width',
        'height',
        'thumbnail',
        'artist',
        'album',
        'duration_seconds',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->path_hash = static::hashPath($item->path);
        });

    }// end booted()

    /**
     * The unique key for a library path (see the migration).
     */
    public static function hashPath(string $path): string
    {
        return sha1($path);

    }// end hashPath()

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'kind' => MediaKind::class,
        ];

    }// end casts()

    /**
     * URL of the file itself, via the gated library location.
     */
    public function url(): string
    {
        $segments = array_map('rawurlencode', explode('/', $this->path));

        return rtrim(Storage::disk('libris')->url(''), '/').'/'.implode('/', $segments);

    }// end url()

    public function thumbnailUrl(): ?string
    {
        if (! $this->thumbnail) {
            return null;
        }

        return Storage::disk('thumbnails')->url($this->thumbnail);

    }// end thumbnailUrl()

    /**
     * Track length as m:ss, or null when unknown.
     */
    public function durationLabel(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($this->duration_seconds, 60), $this->duration_seconds % 60);

    }// end durationLabel()

    public function scopeOfKind(Builder $query, MediaKind $kind): Builder
    {
        return $query->where('kind', $kind->value);

    }// end scopeOfKind()

    /**
     * @return array{art:int,music:int}
     */
    public static function countsForSystem(string $system): array
    {
        $counts = static::query()
            ->where('system', $system)
            ->selectRaw('kind, count(*) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind');

        return [
            'art' => (int) ($counts[MediaKind::Art->value] ?? 0),
            'music' => (int) ($counts[MediaKind::Music->value] ?? 0),
        ];

    }// end countsForSystem()

    /**
     * Every system that has media, keyed by system name.
     *
     * @return Collection<string, array{art:int,music:int}>
     */
    public static function systemsWithMedia(): Collection
    {
        $rows = static::query()
            ->selectRaw('system, kind, count(*) as total')
            ->groupBy('system', 'kind')
            ->orderBy('system')
            ->get();

        $systems = new Collection;
        foreach ($rows as $row) {
            $entry = $systems->get($row->system, ['art' => 0, 'music' => 0]);
            $entry[$row->kind->value] = (int) $row->total;
            $systems->put($row->system, $entry);
        }

        return $systems;

    }// end systemsWithMedia()

    /**
     * Tag => number of items of this kind in the system, sorted by tag.
     *
     * @return array<string,int>
     */
    public static function tagCounts(MediaKind $kind, string $system): array
    {
        $counts = [];
        $lists = static::query()->ofKind($kind)->where('system', $system)->pluck('tags');

        foreach ($lists as $tags) {
            foreach ($tags as $tag) {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;

    }// end tagCounts()

}// end class
