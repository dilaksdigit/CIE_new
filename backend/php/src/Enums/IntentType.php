<?php
namespace App\Enums;
enum IntentType: string {
    case COMPATIBILITY   = 'compatibility';
    case COMPARISON      = 'comparison';
    case PROBLEM_SOLVING = 'problem_solving';
    case SPECIFICATION   = 'specification';
    case INSTALLATION    = 'installation';
    case TROUBLESHOOTING = 'troubleshooting';
    case INSPIRATION     = 'inspiration';
    case REGULATORY      = 'regulatory';
    case REPLACEMENT     = 'replacement';
}
