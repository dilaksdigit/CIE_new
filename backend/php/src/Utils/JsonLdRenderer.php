<?php

namespace App\Utils;

/**
 * CIE v2.3.1 — Schema.org Product JSON-LD renderer by tier.
 * Generates structured data for page <head> based on SKU tier.
 */
class JsonLdRenderer
{
    /**
     * Render Schema.org Product JSON-LD script tag based on SKU tier.
     * 
     * Tier rules:
     * - Hero: Full schema with Product, name, description (answer_block), brand, offers,
     *         material with sameAs Wikidata URI, additionalProperty for Expert Authority, Best For, Not For
     * - Support: Basic schema - Product, name, description, brand, offers
     * - Harvest: Minimal - Product, name, offers only
     * - Kill: Return empty string (no schema)
     * 
     * @param \App\Models\Sku|object $sku SKU model/object with tier, title, ai_answer_block, etc.
     * @return string HTML script tag with JSON-LD or empty string for Kill tier
     */
    public static function renderCieJsonld($sku): string
    {
        $tier = strtolower(trim($sku->tier ?? ''));
        
        // Kill tier: no schema
        if ($tier === 'kill') {
            return '';
        }
        
        // Base schema structure (Product identity + sku for LLM/citation)
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $sku->title ?? '',
            'description' => $sku->ai_answer_block ?? $sku->short_description ?? $sku->description ?? '',
            'sku' => $sku->sku_code ?? '',
        ];
        
        // Brand (from env or config)
        $brandName = env('CIE_BRAND_NAME', config('app.name', 'CIE'));
        $schema['brand'] = [
            '@type' => 'Brand',
            'name' => $brandName,
        ];
        
        // Offers (price, currency, availability)
        $price = $sku->current_price ?? $sku->price ?? null;
        if ($price && $price > 0) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => 'GBP',
                'price' => (float) $price,
                'availability' => 'https://schema.org/InStock',
            ];
        }
        
        // Support and Hero: ensure description from answer_block (may already be set above)
        if (in_array($tier, ['hero', 'support'])) {
            $description = $sku->ai_answer_block ?? $sku->short_description ?? '';
            if ($description) {
                $schema['description'] = $description;
            }
        }
        
        // Hero only: full schema with Wikidata sameAs (array), Expert Authority, Best For, Not For
        // sameAs: Product-level sameAs is an array of Wikidata URLs when wikidata_uri or wikidata_entities
        // is set (spec Patch 3 §3.2). Supports multiple entities e.g. ["Q174102","Q193514"] → full URLs.
        if ($tier === 'hero') {
            $sameAsUrls = self::normalizeWikidataSameAs($sku->wikidata_uri ?? null, $sku->wikidata_entities ?? null);
            if ($sameAsUrls !== []) {
                $schema['sameAs'] = count($sameAsUrls) === 1 ? $sameAsUrls[0] : $sameAsUrls;
                $schema['material'] = [
                    '@type' => 'Text',
                    'name' => $sku->material_name ?? 'Product Material',
                    'sameAs' => $sameAsUrls[0] ?? null,
                ];
            }
            
            // Additional properties: Expert Authority, Best For, Not For
            $additionalProps = [];
            
            if (!empty($sku->expert_authority_name ?? $sku->expert_authority ?? '')) {
                $additionalProps[] = [
                    '@type' => 'PropertyValue',
                    'name' => 'Expert Authority',
                    'value' => $sku->expert_authority_name ?? $sku->expert_authority ?? '',
                ];
            }
            
            // Best For (comma-separated or array)
            $bestFor = $sku->best_for ?? '';
            if ($bestFor) {
                if (is_string($bestFor)) {
                    $bestForArray = array_filter(array_map('trim', explode(',', $bestFor)));
                } else {
                    $bestForArray = is_array($bestFor) ? array_filter($bestFor) : [];
                }
                if (!empty($bestForArray)) {
                    $additionalProps[] = [
                        '@type' => 'PropertyValue',
                        'name' => 'Best For',
                        'value' => implode('; ', $bestForArray),
                    ];
                }
            }
            
            // Not For (comma-separated or array)
            $notFor = $sku->not_for ?? '';
            if ($notFor) {
                if (is_string($notFor)) {
                    $notForArray = array_filter(array_map('trim', explode(',', $notFor)));
                } else {
                    $notForArray = is_array($notFor) ? array_filter($notFor) : [];
                }
                if (!empty($notForArray)) {
                    $additionalProps[] = [
                        '@type' => 'PropertyValue',
                        'name' => 'Not For',
                        'value' => implode('; ', $notForArray),
                    ];
                }
            }
            
            if (!empty($additionalProps)) {
                $schema['additionalProperty'] = $additionalProps;
            }
        }
        
        // Encode Product JSON-LD
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $output = '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';

        // Optional: FAQPage schema for Hero when FAQs present (citability)
        $faqs = $sku->faq_data ?? $sku->faqs ?? null;
        if ($tier === 'hero' && !empty($faqs)) {
            $faqArray = is_string($faqs) ? json_decode($faqs, true) : $faqs;
            if (is_array($faqArray) && count($faqArray) > 0) {
                $mainEntity = [];
                foreach ($faqArray as $faq) {
                    $q = is_array($faq) ? ($faq['question'] ?? $faq['q'] ?? '') : '';
                    $a = is_array($faq) ? ($faq['answer'] ?? $faq['a'] ?? '') : '';
                    if ($q !== '' || $a !== '') {
                        $mainEntity[] = [
                            '@type' => 'Question',
                            'name' => $q,
                            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                        ];
                    }
                }
                if (!empty($mainEntity)) {
                    $faqSchema = [
                        '@context' => 'https://schema.org',
                        '@type' => 'FAQPage',
                        'mainEntity' => $mainEntity,
                    ];
                    $output .= "\n" . '<script type="application/ld+json">' . "\n" . json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n" . '</script>';
                }
            }
        }

        return $output;
    }

    /**
     * Return list of valid Wikidata URLs for sameAs (Hero).
     * Accepts: single URI string, JSON string of URI/array, array of Q-ids or URLs or objects with uri/id.
     */
    private static function normalizeWikidataSameAs($wikidataUri, $wikidataEntities): array
    {
        $out = [];
        $add = static function ($v) use (&$out) {
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '') return;
                if (str_starts_with($v, 'http') && str_contains($v, 'wikidata.org')) {
                    $out[] = $v;
                    return;
                }
                if (preg_match('/^Q\d+$/i', $v)) {
                    $out[] = 'https://www.wikidata.org/wiki/' . $v;
                }
                return;
            }
            if (is_array($v)) {
                if (isset($v['uri'])) { $add($v['uri']); return; }
                if (isset($v['sameAs'])) { $add($v['sameAs']); return; }
                if (isset($v['id'])) { $add($v['id']); return; }
                foreach ($v as $item) $add($item);
            }
        };
        if ($wikidataUri !== null) {
            if (is_string($wikidataUri) && (str_starts_with(trim($wikidataUri), '[') || str_starts_with(trim($wikidataUri), '{'))) {
                $decoded = json_decode($wikidataUri, true);
                $add($decoded);
            } else {
                $add($wikidataUri);
            }
        }
        if ($wikidataEntities !== null) {
            if (is_string($wikidataEntities)) {
                $decoded = json_decode($wikidataEntities, true);
                $add(is_array($decoded) ? $decoded : $wikidataEntities);
            } else {
                $add($wikidataEntities);
            }
        }
        return array_values(array_unique($out));
    }
}
