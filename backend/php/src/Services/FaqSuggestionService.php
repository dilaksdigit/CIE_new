<?php

namespace App\Services;

use App\Models\Sku;

/**
 * CIE v2.3.2 Patch 4 — Auto-generate FAQ blocks from best_for + not_for fields.
 * Returns suggested question/answer pairs for CMS FAQ tab and JSON-LD FAQPage schema.
 */
class FaqSuggestionService
{
    /**
     * Build suggested FAQ blocks for an SKU from best_for and not_for content.
     *
     * @return list<array{question: string, answer: string, source: string}>
     */
    public function suggestFromBestForNotFor(Sku $sku): array
    {
        $blocks = [];
        $bestFor = trim((string) ($sku->best_for ?? ''));
        $notFor = trim((string) ($sku->not_for ?? ''));

        if ($bestFor !== '') {
            $blocks[] = [
                'question' => 'What is this product best used for?',
                'answer'   => $bestFor,
                'source'   => 'best_for',
            ];
            $blocks[] = [
                'question' => 'Which use cases is this product best for?',
                'answer'   => $bestFor,
                'source'   => 'best_for',
            ];
        }

        if ($notFor !== '') {
            $blocks[] = [
                'question' => 'What should I not use this product for?',
                'answer'   => $notFor,
                'source'   => 'not_for',
            ];
            $blocks[] = [
                'question' => 'Is this product suitable for my use case?',
                'answer'   => 'This product is not recommended for: ' . $notFor,
                'source'   => 'not_for',
            ];
        }

        if ($bestFor !== '' && $notFor !== '') {
            $blocks[] = [
                'question' => 'When should I choose this product vs alternatives?',
                'answer'   => 'Best for: ' . $bestFor . ' Not suitable for: ' . $notFor,
                'source'   => 'best_for,not_for',
            ];
        }

        return $blocks;
    }
}
