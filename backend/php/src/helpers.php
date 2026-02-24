<?php

use App\Utils\JsonLdRenderer;

if (!function_exists('render_cie_jsonld')) {
    /**
     * Render CIE Schema.org Product JSON-LD script tag based on SKU tier.
     * 
     * PHP helper function for Blade/Twig templates.
     * 
     * @param \App\Models\Sku|object $sku SKU model/object
     * @return string HTML script tag with JSON-LD or empty string for Kill tier
     */
    function render_cie_jsonld($sku): string
    {
        return JsonLdRenderer::renderCieJsonld($sku);
    }
}
