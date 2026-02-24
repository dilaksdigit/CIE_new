<?php
namespace App\Enums;
enum ValidationStatus: string
{
 case DRAFT = 'DRAFT';
 case PENDING = 'PENDING';
 case VALID = 'VALID';
 case INVALID = 'INVALID';
 case DEGRADED = 'DEGRADED'; // Fail-soft state
 
 public function canPublish(): bool
 {
 return $this === self::VALID;
 }
 
 public function color(): string
 {
 return match($this) {
 self::DRAFT => '#9CA3AF',
 self::PENDING => '#F59E0B',
 self::VALID => '#10B981',
 self::INVALID => '#EF4444',
 self::DEGRADED => '#F97316',
 };
 }
}
