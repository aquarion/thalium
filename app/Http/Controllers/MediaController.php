<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Service\Media\MediaKind;
use App\Service\Media\SystemSections;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    /** Sortable track-list columns => the database column they sort on. */
    private const TRACK_SORTS = [
        'track' => 'track_number',
        'title' => 'title',
        'artist' => 'artist',
        'album' => 'album',
        'length' => 'duration_seconds',
    ];

    public function art(Request $request, SystemSections $sections, string $system): View
    {
        return $this->section($request, $sections, MediaKind::Art, 'media.art', 60, $system);

    }// end art()

    public function music(Request $request, SystemSections $sections, string $system): View
    {
        $sort = $request->query('sort');
        $sort = is_string($sort) && isset(self::TRACK_SORTS[$sort]) ? $sort : 'title';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        return $this->section(
            $request,
            $sections,
            MediaKind::Music,
            'media.music',
            200,
            $system,
            fn (Builder $query) => $this->orderTracks($query, $sort, $dir),
            [
                'sort' => $sort,
                'dir' => $dir,
                // Carried by tag chips so a chosen order survives changing tag.
                'sortQuery' => $request->has('sort') ? ['sort' => $sort, 'dir' => $dir] : [],
            ]
        );

    }// end music()

    /**
     * Rows without a value for the sort column always come last, whichever way
     * it is sorted.
     */
    private function orderTracks(Builder $query, string $sort, string $dir): void
    {
        $column = self::TRACK_SORTS[$sort];

        $query->orderByRaw("$column IS NULL")->orderBy($column, $dir);

        // Ties fall back to album/track/title and finally the id, so every listing has a
        // total order: paging over repeated titles can't repeat or skip rows on MySQL.
        $tieBreakers = match ($sort) {
            'artist' => ['album', 'track_number', 'title', 'id'],
            'album' => ['track_number', 'title', 'id'],
            default => ['title', 'id'],
        };

        foreach ($tieBreakers as $tieBreaker) {
            $query->orderBy($tieBreaker);
        }

    }// end orderTracks()

    private function section(
        Request $request,
        SystemSections $sections,
        MediaKind $kind,
        string $view,
        int $perPage,
        string $system,
        ?callable $order = null,
        array $extra = []
    ): View {
        $tag = $request->query('tag');
        $tag = is_string($tag) && $tag !== '' ? $tag : null;

        $query = MediaItem::query()->ofKind($kind)->where('system', $system);

        if ($tag) {
            $query->whereJsonContains('tags', $tag);
        }

        $order ? $order($query) : $query->orderBy('title')->orderBy('id');

        return view($view, [
            'system' => $system,
            'tag' => $tag,
            'tagList' => MediaItem::tagCounts($kind, $system),
            'items' => $query->paginate($perPage)->withQueryString(),
            'counts' => $sections->counts($system, $tag),
        ] + $extra);

    }// end section()

}// end class
