<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — BusinessRules layer
// All thresholds read from business_rules table. Zero hard-coded values permitted.

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G3_SEOGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        $maxMetaTitle       = (int) BusinessRules::get('gates.meta_title_max_chars');
        $maxMetaDescription = (int) BusinessRules::get('gates.meta_description_max_chars');
        $minMetaDescription = (int) BusinessRules::get('gates.meta_description_min_chars');

        $issues = [];

        if (!$sku->meta_title) {
            $issues[] = 'Meta title is missing';
        } elseif (strlen($sku->meta_title) > $maxMetaTitle) {
            $issues[] = sprintf('Meta title too long (%d chars, max %d)',
                strlen($sku->meta_title), $maxMetaTitle);
        }

        if (!$sku->meta_description) {
            $issues[] = 'Meta description is missing';
        } elseif (strlen($sku->meta_description) > $maxMetaDescription) {
            $issues[] = sprintf('Meta description too long (%d chars, max %d)',
                strlen($sku->meta_description), $maxMetaDescription);
        } elseif (strlen($sku->meta_description) < $minMetaDescription) {
            $issues[] = sprintf('Meta description too short (%d chars, min %d)',
                strlen($sku->meta_description), $minMetaDescription);
        }

        if (count($issues) > 0) {
            return new GateResult(
                gate: GateType::G3_SEO,
                passed: false,
                reason: implode('. ', $issues),
                blocking: true
            );
        }

        return new GateResult(
            gate: GateType::G3_SEO,
            passed: true,
            reason: sprintf('SEO metadata valid (title: %d chars, description: %d chars)',
                strlen($sku->meta_title), strlen($sku->meta_description)),
            blocking: false
        );
    }
}
