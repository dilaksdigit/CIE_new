<?php

require 'backend/php/vendor/autoload.php';

// Mock class to access private method for testing
class TestG4 extends \App\Validators\Gates\G4_AnswerBlockGate {
    public function testStemming($intent) {
        $reflection = new ReflectionClass($this);
        $method = $reflection->getMethod('getStemmedKeyword');
        $method->setAccessible(true);
        return $method->invoke($this, $intent);
    }
}

$tester = new TestG4();
$intents = [
    'installation' => 'install',
    'troubleshooting' => 'shoot',
    'regulatory' => 'safe',
    'replacement' => 'replac'
];

$pass = true;
foreach ($intents as $intent => $expected) {
    try {
        $result = $tester->testStemming($intent);
        if ($result !== $expected) {
            echo "FAIL: '$intent' expected '$expected', got '$result'\n";
            $pass = false;
        } else {
            echo "PASS: '$intent' -> '$result'\n";
        }
    } catch (Exception $e) {
        // Fallback if class not found due to autoloading issues in this env
        echo "SKIP: Could not load class ($intent). Manual check required.\n";
        $pass = false;
        break;
    }
}

if ($pass) {
    echo "\nAll stemming rules verified.\n";
} else {
    echo "\nVerification FAILED.\n";
}
