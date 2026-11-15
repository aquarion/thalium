<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Service\Media\MediaKind;
use App\Service\Media\SystemSections;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function art(Request $request, SystemSections $sections, string $system): View
    {
        return $this->section($request, $sections, MediaKind::Art, 'media.art', 60, $system);

    }// end art()

    public function music(Request $request, SystemSections $sections, string $system): View
    {
        return $this->section($request, $sections, MediaKind::Music, 'media.music', 200, $system);

    }// end music()

    private function section(
        Request $request,
        SystemSections $sections,
        MediaKind $kind,
        string $view,
        int $perPage,
        string $system
    ): View {
        $tag = $request->query('tag');

        $query = MediaItem::query()->ofKind($kind)->where('system', $system)->orderBy('title');

        if ($tag) {
            $query->whereJsonContains('tags', $tag);
        }

        return view($view, [
            'system' => $system,
            'tag' => $tag,
            'tagList' => MediaItem::tagCounts($kind, $system),
            'items' => $query->paginate($perPage)->withQueryString(),
            'counts' => $sections->counts($system),
        ]);

    }// end section()

}// end class
