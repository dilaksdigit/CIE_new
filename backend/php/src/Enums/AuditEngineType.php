<?php
namespace App\Enums;
enum AuditEngineType: string {
    case OPENAI = 'OPENAI';
    case ANTHROPIC = 'ANTHROPIC';
}
