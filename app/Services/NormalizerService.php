<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class NormalizerService
{
    public function normalize(string $text): string
    {
        $text = $this->trimAndCollapse($text);
        $text = $this->toLowercase($text);
        $text = $this->unifyArabicLetters($text);
        $text = $this->removeDiacritics($text);
        $text = $this->removeSymbols($text);
        $text = $this->unifyMedicalAbbreviations($text);
        $text = $this->finalCollapse($text);

        return $text;
    }

    private function trimAndCollapse(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text;
    }

    private function toLowercase(string $text): string
    {
        return mb_strtolower($text, 'UTF-8');
    }

    private function unifyArabicLetters(string $text): string
    {
        $replacements = [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ٱ' => 'ا',
            'ى' => 'ي',
            'ئ' => 'ي',
            'ة' => 'ه',
            'ؤ' => 'و',
            'ء' => '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function removeDiacritics(string $text): string
    {
        return preg_replace('/[\x{064B}-\x{065F}]/u', '', $text) ?? $text;
    }

    private function removeSymbols(string $text): string
    {
        $text = preg_replace('/[^\p{Arabic}\p{Latin}\d\s]/u', ' ', $text) ?? $text;

        return $text;
    }

    private function unifyMedicalAbbreviations(string $text): string
    {
        $abbreviations = [
            '/\b(\d+)\s*milligram[s]?\b/i' => '$1mg',
            '/\b(\d+)\s*microgram[s]?\b/i' => '$1mcg',
            '/\b(\d+)\s*gram[s]?\b/i' => '$1g',
            '/\b(\d+)\s*ml\b/i' => '$1ml',
            '/\b(\d+)\s*iu\b/i' => '$1iu',
            '/\btablets?\b/i' => 'tab',
            '/\tcapsules?\b/i' => 'cap',
            '/\bsyrup\b/i' => 'syr',
            '/\binjection\b/i' => 'inj',
            '/\bsuppository\b/i' => 'supp',
            '/\bcream\b/i' => 'cr',
            '/\bointment\b/i' => 'oint',
            '/\bsolution\b/i' => 'sol',
            '/\bsuspension\b/i' => 'susp',
            '/\bأقراص\b/u' => 'tab',
            '/\bكبسول\b/u' => 'cap',
            '/\bشراب\b/u' => 'syr',
            '/\bحقن\b/u' => 'inj',
            '/\bمرهم\b/u' => 'oint',
            '/\bقطرة\b/u' => 'drops',
            '/\bقطر\b/u' => 'drops',
        ];

        return preg_replace(array_keys($abbreviations), array_values($abbreviations), $text) ?? $text;
    }

    private function finalCollapse(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * ICU transliteration to Latin ASCII so Arabic queries can match Latin brand spellings (e.g. Obunof).
     */
    public function transliterateToLatinAscii(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (class_exists(\Transliterator::class)) {
            try {
                static $tr = null;
                if ($tr === null) {
                    $tr = \Transliterator::create('Any-Latin; Latin-ASCII');
                }
                $out = $tr->transliterate($text);

                return is_string($out) ? $out : $text;
            } catch (\Throwable) {
                // fall through
            }
        }

        return $this->transliterateToLatinAsciiFallback($text);
    }

    /**
     * Consonant-heavy fold for cross-script "sounds like" matching (Arabic اوبونوف vs Latin obunof).
     */
    public function phoneticConsonantKey(string $text): string
    {
        $ascii = $this->transliterateToLatinAscii($text);
        $ascii = mb_strtolower($ascii, 'UTF-8');
        $ascii = preg_replace('/[aeiou\s\-_\'\.]/u', '', $ascii) ?? $ascii;
        $ascii = preg_replace('/w+/u', '', $ascii) ?? $ascii;

        return $ascii;
    }

    /**
     * @return list<string>
     */
    public function likeTermsForSearch(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $normalized = $this->normalize($raw);
        $lower = mb_strtolower($raw, 'UTF-8');
        $latin = $this->transliterateToLatinAscii($raw);
        $latinLower = $latin !== '' ? mb_strtolower($latin, 'UTF-8') : '';
        $latinNorm = $latin !== '' ? $this->normalize($latin) : '';

        $terms = array_unique(array_filter([
            $normalized,
            $lower,
            $latinLower,
            $latinNorm,
        ], fn (string $t) => $t !== ''));

        return array_values($terms);
    }

    public function applyFlexibleProductSearch(Builder $builder, string $raw): void
    {
        $terms = $this->likeTermsForSearch($raw);
        if ($terms === []) {
            return;
        }

        $builder->where(function (Builder $outer) use ($terms) {
            foreach ($terms as $term) {
                $safe = $this->stripUnsafeLikeWildcards($term);
                if ($safe === '') {
                    continue;
                }

                $like = '%'.$safe.'%';

                $outer->orWhere(function (Builder $inner) use ($like) {
                    $inner->where('name', 'LIKE', $like)
                        ->orWhere('code', 'LIKE', $like);
                });
            }
        });
    }

    public function applyPrefixProductSearch(Builder $builder, string $raw): void
    {
        $terms = $this->likeTermsForSearch($raw);
        if ($terms === []) {
            return;
        }

        $builder->where(function (Builder $outer) use ($terms) {
            foreach ($terms as $term) {
                $safe = $this->stripUnsafeLikeWildcards($term);
                if ($safe === '') {
                    continue;
                }

                $like = $safe.'%';

                $outer->orWhere(function (Builder $inner) use ($like) {
                    $inner->where('name', 'LIKE', $like)
                        ->orWhere('code', 'LIKE', $like);
                });
            }
        });
    }

    private function stripUnsafeLikeWildcards(string $value): string
    {
        return str_replace(['%', '_'], '', $value);
    }

    private function transliterateToLatinAsciiFallback(string $text): string
    {
        $map = [
            'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ٱ' => 'a', 'ا' => 'a',
            'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh',
            'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh',
            'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh',
            'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
            'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'h', 'ئ' => 'y', 'ؤ' => 'w',
        ];

        $out = str_replace(array_keys($map), array_values($map), $text);

        return preg_replace('/[^\x20-\x7E]/u', '', $out) ?? $out;
    }
}
