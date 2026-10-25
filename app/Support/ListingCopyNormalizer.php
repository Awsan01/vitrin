<?php

namespace App\Support;

use App\Enums\Marketplace;

/**
 * Enforces the marketplace rules on what the model returned. Models usually
 * follow the limits, but "usually" isn't good enough for a 75-character
 * Amazon title, so every hard limit is checked here and anything changed is
 * reported back to the seller as a warning.
 */
class ListingCopyNormalizer
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array{title: string, highlight: string|null, bullets: list<string>, description: string, keywords: list<string>, category: string|null, warnings: list<string>}
     */
    public static function normalize(array $raw, Marketplace $marketplace): array
    {
        $rules = $marketplace->rules();
        $market = $marketplace->label();
        $warnings = [];

        // Title
        $title = rtrim(self::line($raw['title'] ?? ''), ' .,;:-–|');
        $length = mb_strlen($title);

        if ($length > $rules['title_max']) {
            $title = self::shorten($title, $rules['title_max']);
            $warnings[] = "Title shortened from {$length} to ".mb_strlen($title)." characters to fit {$market}'s {$rules['title_max']}-character limit. Check that it still reads well.";
        }

        if (isset($rules['title_min']) && mb_strlen($title) < $rules['title_min']) {
            $warnings[] = "Title is shorter than {$rules['title_min']} characters, which {$market} may reject.";
        }

        // Highlight (Amazon item highlights, store meta description)
        $highlight = null;

        if ($rules['highlight']) {
            $highlight = self::line($raw['highlight'] ?? '') ?: null;
            $max = $rules['highlight']['max'];

            if ($highlight !== null && mb_strlen($highlight) > $max) {
                $highlight = self::shorten($highlight, $max);
                $warnings[] = "{$rules['highlight']['label']} shortened to {$max} characters.";
            }
        }

        // Bullets
        $bullets = collect(self::strings($raw['bullets'] ?? []))
            ->map(fn ($b) => self::line(preg_replace('/^\s*(?:[-•*]|\d+[.)])\s+/u', '', (string) $b)))
            ->filter()
            ->unique(fn ($b) => mb_strtolower($b))
            ->values();

        if ($bullets->count() > $rules['bullets']) {
            $bullets = $bullets->take($rules['bullets']);
        }

        $shortenedBullets = 0;
        $bullets = $bullets->map(function (string $b) use ($rules, &$shortenedBullets) {
            if (mb_strlen($b) <= $rules['bullet_max']) {
                return $b;
            }

            $shortenedBullets++;

            return self::shorten($b, $rules['bullet_max']);
        })->all();

        if ($shortenedBullets > 0) {
            $warnings[] = "{$shortenedBullets} bullet point(s) shortened to {$rules['bullet_max']} characters.";
        }

        if (count($bullets) < $rules['bullets']) {
            $warnings[] = 'Only '.count($bullets)." of {$rules['bullets']} bullet points were written. Consider adding more.";
        }

        // Keywords / tags
        $spec = $rules['keywords'];
        $keywords = collect(self::strings($raw['keywords'] ?? []))
            ->map(fn ($k) => trim(self::line((string) $k), ' ,;#'))
            ->filter()
            ->unique(fn ($k) => mb_strtolower($k))
            ->values();

        $tooLong = $keywords->filter(fn ($k) => mb_strlen($k) > $spec['max_length']);

        if ($tooLong->isNotEmpty()) {
            $keywords = $keywords->reject(fn ($k) => mb_strlen($k) > $spec['max_length'])->values();
            $warnings[] = "Removed {$tooLong->count()} {$spec['label']} entr".($tooLong->count() === 1 ? 'y' : 'ies')." longer than {$spec['max_length']} characters.";
        }

        $keywords = $keywords->take($spec['count'])->all();

        if ($spec['max_bytes']) {
            $dropped = 0;

            while ($keywords !== [] && strlen(implode(' ', $keywords)) > $spec['max_bytes']) {
                array_pop($keywords);
                $dropped++;
            }

            if ($dropped > 0) {
                $warnings[] = "Dropped {$dropped} {$spec['label']} entr".($dropped === 1 ? 'y' : 'ies')." to stay within {$spec['max_bytes']} bytes.";
            }
        }

        return [
            'title' => $title,
            'highlight' => $highlight,
            'bullets' => array_values($bullets),
            'description' => self::text($raw['description'] ?? ''),
            'keywords' => array_values($keywords),
            'category' => mb_substr(self::line($raw['category'] ?? ''), 0, 255) ?: null,
            'warnings' => $warnings,
        ];
    }

    /**
     * Model output for list fields, as a list of strings.
     *
     * @return list<string>
     */
    protected static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
            $value,
        ));
    }

    /**
     * A single line of text: no emoji, no line breaks, single spaces.
     */
    protected static function line(mixed $value): string
    {
        $value = self::stripEmoji((string) $value);
        $value = str_replace(['**', '__', '`'], '', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * Multi-line text: keeps paragraphs and "- " list lines, drops markdown.
     */
    protected static function text(mixed $value): string
    {
        $value = self::stripEmoji(str_replace(["\r\n", "\r"], "\n", (string) $value));
        $value = str_replace(['**', '__', '`'], '', $value);
        $value = (string) preg_replace('/^\s{0,3}#{1,6}\s*/mu', '', $value);
        $value = (string) preg_replace('/^[ \t]*[•*][ \t]+/mu', '- ', $value);
        $value = (string) preg_replace('/[ \t]+$/mu', '', $value);
        $value = (string) preg_replace("/\n{3,}/", "\n\n", $value);

        return trim($value);
    }

    /**
     * Cut to $max characters, backing up to a word boundary when possible.
     */
    protected static function shorten(string $value, int $max): string
    {
        $cut = mb_substr($value, 0, $max);

        if (mb_strlen($value) > $max && mb_substr($value, $max, 1) !== ' ') {
            $lastSpace = mb_strrpos($cut, ' ');

            if ($lastSpace !== false && $lastSpace > $max * 0.6) {
                $cut = mb_substr($cut, 0, $lastSpace);
            }
        }

        return rtrim($cut, ' .,;:-–|/');
    }

    protected static function stripEmoji(string $value): string
    {
        return (string) preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $value);
    }
}
