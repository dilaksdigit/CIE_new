<?php
namespace App\Validators\Gates;
use App\Models\Sku;
use App\Enums\GateType;
use App\Validators\GateResult;
use App\Validators\GateInterface;
class G2_ImagesGate implements GateInterface
{
 public function validate(Sku $sku): GateResult
 {
 if (!$sku->primary_image || !file_exists(storage_path('uploads/images/' . $sku->primary_image))) {
 return new GateResult(
 gate: GateType::G2_IMAGES,
 passed: false,
 reason: 'At least one hero image is required. Upload a primary product image.',
 blocking: true
 );
 }
 
 $filePath = storage_path('uploads/images/' . $sku->primary_image);
 $fileSize = filesize($filePath);
 if ($fileSize > 5 * 1024 * 1024) {
 return new GateResult(
 gate: GateType::G2_IMAGES,
 passed: false,
 reason: 'Primary image exceeds 5MB limit. Compress or resize the image.',
 blocking: true
 );
 }
 
 $imageInfo = getimagesize($filePath);
 if ($imageInfo[0] < 800 || $imageInfo[1] < 800) {
 return new GateResult(
 gate: GateType::G2_IMAGES,
 passed: false,
 reason: 'Primary image must be at least 800x800 pixels for quality standards.',
 blocking: true
 );
 }
 
 return new GateResult(
 gate: GateType::G2_IMAGES,
 passed: true,
 reason: sprintf('Primary image uploaded (%dx%d, %.1f MB)',
 $imageInfo[0], $imageInfo[1], $fileSize / 1024 / 1024),
 blocking: false
 );
 }
}
