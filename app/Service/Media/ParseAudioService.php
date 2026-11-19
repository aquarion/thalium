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
            $info = (new \getID3)->analyze(Storage::disk($this->disk)->path($this->filename));
        } catch (\Throwable $e) {
            Log::warning("[Parser] Could not read audio tags for {$this->filename}: ".$e->getMessage(), ['exception' => $e]);

            return [];
        }

        // getID3 reports most unreadable or corrupt files here instead of throwing.
        if (! empty($info['error'])) {
            Log::warning("[Parser] getID3 could not fully read {$this->filename}: ".implode('; ', (array) $info['error']));
        }

        $metadata = [];

        foreach (['title', 'artist', 'album'] as $field) {
            $value = $this->firstTag($info, $field);
            if ($value !== null) {
                $metadata[$field] = $value;
            }
        }

        // ID3 keeps it as "3" or "3/12"; Vorbis comments call it "tracknumber".
        $track = $this->firstTag($info, 'track_number')
            ?? $this->firstTag($info, 'tracknumber')
            ?? $this->firstTag($info, 'track');
        if ($track !== null && (int) $track > 0) {
            $metadata['track_number'] = min((int) $track, 65535);
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
