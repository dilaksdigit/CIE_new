<?php
namespace App\Validators\Gates;
use App\Models\Sku;
use App\Enums\GateType;
use App\Validators\GateResult;
use App\Validators\GateInterface;
class G6_CommercialGate implements GateInterface
{
 public function validate(Sku $sku): GateResult
 {
 $missing = [];
 if (!$sku->current_price || $sku->current_price <= 0) {
 $missing[] = 'Valid price';
 }
 if (!isset($sku->margin_percent)) {
 $missing[] = 'Margin data';
 }
 if (!$sku->last_sale_date) {
 $missing[] = 'Last sale date';
 }
 
 if (count($missing) > 0) {
 return new GateResult(
 gate: GateType::G6_COMMERCIAL,
 passed: false,
 reason: 'Missing ERP data: ' . implode(', ', $missing) . '. Ensure nightly ERP sync has completed.',
 blocking: true
 );
 }
 
 return new GateResult(
 gate: GateType::G6_COMMERCIAL,
 passed: true,
 reason: sprintf('Commercial data synced (price: $%.2f, margin: %.1f%%)',
 $sku->current_price, $sku->margin_percent),
 blocking: false
 );
 }
}
