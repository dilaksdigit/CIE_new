<?php
namespace App\Validators\Gates;
use App\Models\Sku;
use App\Enums\GateType;
use App\Validators\GateResult;
use App\Validators\GateInterface;
class G3_SEOGate implements GateInterface
{
 private const MAX_META_TITLE = 60;
 private const MAX_META_DESCRIPTION = 160;
 
 public function validate(Sku $sku): GateResult
 {
 $issues = [];
 
 if (!$sku->meta_title) {
 $issues[] = 'Meta title is missing';
 } elseif (strlen($sku->meta_title) > self::MAX_META_TITLE) {
 $issues[] = sprintf('Meta title too long (%d chars, max %d)',
 strlen($sku->meta_title), self::MAX_META_TITLE);
 }
 
 if (!$sku->meta_description) {
 $issues[] = 'Meta description is missing';
 } elseif (strlen($sku->meta_description) > self::MAX_META_DESCRIPTION) {
 $issues[] = sprintf('Meta description too long (%d chars, max %d)',
 strlen($sku->meta_description), self::MAX_META_DESCRIPTION);
 } elseif (strlen($sku->meta_description) < 50) {
 $issues[] = 'Meta description too short (min 50 characters for effective SEO)';
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
