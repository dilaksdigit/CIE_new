<?php
namespace App\Validators;
use App\Models\Sku;
interface GateInterface
{
 public function validate(Sku $sku): GateResult;
}
