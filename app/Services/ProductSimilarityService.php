<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductSimilarityService
{
    public function __construct(private NormalizerService $normalizer) {}

    /**
     * Find products similar to the given name with similarity scores
     * 
     * @param  string  $name  The product name to find matches for
     * @param  int  $companyId  The company ID to scope the search
     * @param  float  $threshold  Minimum similarity threshold (0-1)
     * @param  int  $limit  Maximum number of results
     * @return Collection<int, array{product: Product, similarity: float}>
     */
    public function findSimilarProducts(string $name, int $companyId, float $threshold = 0.75, int $limit = 5): Collection
    {
        $normalizedQuery = $this->normalizer->normalize($name);
        
        // First, try exact match with normalization
        $exactMatch = Product::where('company_id', $companyId)
            ->where('name', $normalizedQuery)
            ->first();
        
        if ($exactMatch) {
            return collect([[
                'product' => $exactMatch,
                'similarity' => 1.0,
            ]]);
        }

        // Get all products from the company
        $products = Product::where('company_id', $companyId)
            ->get();

        // Calculate similarity for each product
        $similarProducts = $products->map(function (Product $product) use ($name, $normalizedQuery) {
            $similarity = $this->calculateSimilarity($name, $product->name);
            
            return [
                'product' => $product,
                'similarity' => $similarity,
            ];
        })
        ->filter(fn ($item) => $item['similarity'] >= $threshold)
        ->sortByDesc('similarity')
        ->take($limit)
        ->values();

        return $similarProducts;
    }

    /**
     * Calculate similarity between two product names
     * Uses multiple methods: similar_text, first word matching, and phonetic matching
     * 
     * @param  string  $name1  First product name
     * @param  string  $name2  Second product name
     * @return float  Similarity score between 0 and 1
     */
    public function calculateSimilarity(string $name1, string $name2): float
    {
        $norm1 = $this->normalizer->normalize($name1);
        $norm2 = $this->normalizer->normalize($name2);

        // Exact match
        if ($norm1 === $norm2) {
            return 1.0;
        }

        // Empty strings
        if (empty($norm1) || empty($norm2)) {
            return 0.0;
        }

        // Method 1: similar_text (string similarity)
        similar_text($norm1, $norm2, $percent1);
        $score1 = $percent1 / 100;

        // Method 2: First word similarity (for "أوميز" vs "اميز")
        $score2 = $this->firstWordSimilarity($norm1, $norm2);

        // Method 3: Phonetic consonant key matching
        $score3 = $this->phoneticSimilarity($norm1, $norm2);

        // Weighted average: similar_text (50%), first word (30%), phonetic (20%)
        $finalScore = ($score1 * 0.5) + ($score2 * 0.3) + ($score3 * 0.2);

        return min($finalScore, 1.0);
    }

    /**
     * Calculate similarity based on first word matching
     * Useful for detecting "أوميز" vs "اميز" type variations
     */
    private function firstWordSimilarity(string $name1, string $name2): float
    {
        $word1 = $this->getFirstWord($name1);
        $word2 = $this->getFirstWord($name2);

        if (mb_strlen($word1) < 2 || mb_strlen($word2) < 2) {
            return 0.0;
        }

        if ($word1 === $word2) {
            return 1.0;
        }

        // Check if one edit or less apart (for "أميز" vs "اميز")
        if ($this->isOneEditOrLess($word1, $word2)) {
            return 0.9;
        }

        similar_text($word1, $word2, $percent);
        return $percent / 100;
    }

    /**
     * Calculate phonetic similarity using consonant keys
     * Useful for cross-script matching (Arabic vs Latin)
     */
    private function phoneticSimilarity(string $name1, string $name2): float
    {
        $key1 = $this->normalizer->phoneticConsonantKey($name1);
        $key2 = $this->normalizer->phoneticConsonantKey($name2);

        if (empty($key1) || empty($key2)) {
            return 0.0;
        }

        if ($key1 === $key2) {
            return 1.0;
        }

        if (str_contains($key2, $key1) || str_contains($key1, $key2)) {
            return 0.8;
        }

        similar_text($key1, $key2, $percent);
        return $percent / 100;
    }

    /**
     * Extract first word from a string
     */
    private function getFirstWord(string $text): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return $words[0] ?? '';
    }

    /**
     * Check if two strings are one edit or less apart (insert, delete, or replace)
     * From Dwaa SearchService
     */
    private function isOneEditOrLess(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $lenA = mb_strlen($a);
        $lenB = mb_strlen($b);
        if (abs($lenA - $lenB) > 1) {
            return false;
        }

        $charsA = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $charsB = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $i = 0;
        $j = 0;
        $edits = 0;

        while ($i < count($charsA) && $j < count($charsB)) {
            if ($charsA[$i] === $charsB[$j]) {
                $i++;
                $j++;
                continue;
            }

            $edits++;
            if ($edits > 1) {
                return false;
            }

            if ($lenA > $lenB) {
                $i++;
            } elseif ($lenB > $lenA) {
                $j++;
            } else {
                $i++;
                $j++;
            }
        }

        if ($i < count($charsA) || $j < count($charsB)) {
            $edits++;
        }

        return $edits <= 1;
    }

    /**
     * Detect similar products in a batch of rows
     * Used during Excel import to flag potential duplicates
     * 
     * @param  array<int, array{product_name: string}>  $rows
     * @param  int  $companyId
     * @return array<string, array{original: string, similar: array{product: Product, similarity: float}[]>>
     */
    public function detectSimilarInBatch(array $rows, int $companyId): array
    {
        $similarities = [];
        $processedNames = [];

        foreach ($rows as $row) {
            $originalName = trim($row['product_name'] ?? '');
            
            if (empty($originalName)) {
                continue;
            }

            $normalized = $this->normalizer->normalize($originalName);
            
            // Skip if already processed this normalized name
            if (isset($processedNames[$normalized])) {
                continue;
            }

            $processedNames[$normalized] = true;

            // Find similar products
            $similar = $this->findSimilarProducts($originalName, $companyId, 0.75, 3);

            // Filter out exact matches (similarity = 1.0) - these should pass automatically
            $similar = $similar->filter(fn ($item) => $item['similarity'] < 1.0);

            if ($similar->isNotEmpty()) {
                $similarities[$originalName] = [
                    'original' => $originalName,
                    'similar' => $similar->toArray(),
                ];
            }
        }

        return $similarities;
    }
}
