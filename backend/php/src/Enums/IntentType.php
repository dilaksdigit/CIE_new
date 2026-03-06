<?php
namespace App\Enums;
enum IntentType: string {
    case COMPATIBILITY     = 'compatibility';
    case COMPARISON        = 'comparison';
    case PROBLEM_SOLVING   = 'problem_solving';
    case INSPIRATION       = 'inspiration';
    case SPECIFICATION     = 'specification';
    case INSTALLATION      = 'installation';
    case SAFETY_COMPLIANCE = 'safety_compliance';
    case REPLACEMENT       = 'replacement';
    case BULK_TRADE        = 'bulk_trade';
}
