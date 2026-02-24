<?php
namespace App\Enums;
enum TierType: string
{
 case HERO = 'HERO';
 case SUPPORT = 'SUPPORT';
 case HARVEST = 'HARVEST';
 case KILL = 'KILL';
 
 public function displayName(): string
 {
 return match($this) {
 self::HERO => 'Hero',
 self::SUPPORT => 'Support',
 self::HARVEST => 'Harvest',
 self::KILL => 'Kill',
 };
 }
 
 public function description(): string
 {
 return match($this) {
 self::HERO => 'Top 20% margin+volume products',
 self::SUPPORT => 'Profitable products',
 self::HARVEST => 'Low margin but still selling',
 self::KILL => 'Negative margin or no sales',
 };
 }
 
 public function color(): string
 {
 return match($this) {
 self::HERO => '#10B981', // Green
 self::SUPPORT => '#3B82F6', // Blue
 self::HARVEST => '#F59E0B', // Yellow
 self::KILL => '#EF4444', // Red
 };
 }
 
 public function fieldsAreLocked(): bool
 {
  return in_array($this, [self::HARVEST, self::KILL]);
 }
}
