<?php

namespace App\Models;

use App\Service\Media\MediaKind;
use App\Service\Media\MediaSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MediaItem extends Model
{
    protected $fillable = [
        'path',
        'path_hash',
        'source',
        'kind',
        'system',
        'collection',
        'tags',
        'title',
        'mtime',
        'size',
        'width',
        'height',
        'thumbnail',
        'artist',
        'album',
        'track_number',
        'duration_seconds',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->path_hash = static::hashPath($item->path, $item->source ?? MediaSource::Library);
        });

    }// end booted()

    /**
     * The unique key for a path in a source (see the migration). Library keys are
     * the bare path hash, as they always were; the maps tree is hashed apart so the
     * same relative path in both libraries makes two rows.
     */
    public static function hashPath(string $path, MediaSource $source = MediaSource::Library): string
    {
        return $source === MediaSource::Library ? sha1($path) : sha1($source->value."\0".$path);

    }// end hashPath()

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'kind' => MediaKind::class,
            'source' => MediaSource::class,
        ];

    }// end casts()

    /**
     * URL of the file itself, via the gated library location.
     */
    public function url(): string
    {
        $segments = array_map('rawurlencode', explode('/', $this->path));
        $disk = ($this->source ?? MediaSource::Library)->disk();

        return rtrim(Storage::disk($disk)->url(''), '/').'/'.implode('/', $segments);

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
     * Rows that belong to a system: those filed under it, plus maps from the maps tree
     * whose collection is the system's name (matched here, not stored, so it never goes
     * stale as systems come and go). A collection that is no system stays unmatched.
     */
    public function scopeForSystem(Builder $query, string $system): Builder
    {
        return $query->where(function (Builder $query) use ($system) {
            $query->where('system', $system)
                ->orWhere(function (Builder $query) use ($system) {
                    $query->where('source', MediaSource::Maps->value)
                        ->whereRaw('lower(collection) = ?', [mb_strtolower($system)]);
                });
        });

    }// end scopeForSystem()

    /**
     * Items per kind in a system, optionally only those carrying a tag. Art and music
     * belong to the system itself; maps also include the matching maps collection.
     *
     * @return array{art:int,music:int,map:int}
     */
    public static function countsForSystem(string $system, ?string $tag = null): array
    {
        $count = fn (Builder $query) => $query
            ->when($tag, fn ($query) => $query->whereJsonContains('tags', $tag))
            ->count();

        return [
            'art' => $count(static::query()->ofKind(MediaKind::Art)->where('system', $system)),
            'music' => $count(static::query()->ofKind(MediaKind::Music)->where('system', $system)),
            'map' => $count(static::query()->ofKind(MediaKind::Map)->forSystem($system)),
        ];

    }// end countsForSystem()

    /**
     * Every system that has media, keyed by system name.
     *
     * @return Collection<string, array{art:int,music:int,map:int}>
     */
    public static function systemsWithMedia(): Collection
    {
        $rows = static::query()
            ->whereNotNull('system')
            ->selectRaw('system, kind, count(*) as total')
            ->groupBy('system', 'kind')
            ->orderBy('system')
            ->get();

        $systems = new Collection;
        foreach ($rows as $row) {
            $entry = $systems->get($row->system, ['art' => 0, 'music' => 0, 'map' => 0]);
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
        $query = static::query()->ofKind($kind);
        if ($kind === MediaKind::Map) {
            $query->forSystem($system);
        } else {
            $query->where('system', $system);
        }

        return static::countTags($query->pluck('tags'));

    }// end tagCounts()

    /**
     * Tag => number of maps carrying it, across every system and collection.
     *
     * @return array<string,int>
     */
    public static function mapTagCounts(): array
    {
        return static::countTags(static::query()->ofKind(MediaKind::Map)->pluck('tags'));

    }// end mapTagCounts()

    /**
     * All maps, optionally those carrying a tag, grouped by system (or by collection for
     * maps that belong to no system) and sorted by group name, then title.
     *
     * @return Collection<string, Collection<int, MediaItem>>
     */
    public static function mapGroups(?string $tag = null): Collection
    {
        return static::query()
            ->ofKind(MediaKind::Map)
            ->when($tag, fn ($query) => $query->whereJsonContains('tags', $tag))
            ->orderBy('title')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (self $item) => $item->system ?? $item->collection)
            ->sortKeysUsing('strcasecmp');

    }// end mapGroups()

    /**
     * @param  iterable<array<string>>  $lists
     * @return array<string,int>
     */
    private static function countTags(iterable $lists): array
    {
        $counts = [];

        foreach ($lists as $tags) {
            foreach ($tags as $tag) {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;

    }// end countTags()

}// end class
