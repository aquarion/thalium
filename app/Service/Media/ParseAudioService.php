<?php

namespace App\Service\Media;

use App\Service\ParserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ParseAudioService extends ParserService implements MediaParserInterface
{
    public function kind(): MediaKind
    {
        return MediaKind::Music;

    }// end kind()

    public function parsePages()
    {
        return [];

    }// end parsePages()

    public function metadata(): array
    {
        try {
            $info = (new \getID3)->analyze(Storage::disk('libris')->path($this->filename));
        } catch (\Throwable $e) {
            Log::warning("[Parser] Could not read audio tags for {$this->filename}: ".$e->getMessage());

            return [];
        }

        $metadata = [];

        foreach (['title', 'artist', 'album'] as $field) {
            $value = $this->firstTag($info, $field);
            if ($value !== null) {
                $metadata[$field] = $value;
            }
        }

        $playtime = $info['playtime_seconds'] ?? null;
        if ($playtime) {
            $metadata['duration_seconds'] = (int) round($playtime);
        }

        return $metadata;

    }// end metadata()

    private function firstTag(array $info, string $field): ?string
    {
        foreach ($info['tags'] ?? [] as $container) {
            $value = trim((string) ($container[$field][0] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;

    }// end firstTag()

}// end class
