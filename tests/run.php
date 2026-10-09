<?php
declare(strict_types=1);

/**
 * Automated Test Runner for Content Intelligence Plugin.
 * Executes all unit and security test suites and outputs a formatted report.
 */

// Load root composer autoloader
$vendorAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// If Craft bootstrap is available, include it
$craftBootstrap = dirname(__DIR__, 3) . '/bootstrap.php';
if (file_exists($craftBootstrap)) {
    require_once $craftBootstrap;
    if (defined('CRAFT_VENDOR_PATH')) {
        require_once CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
    }
}

// Load test assertions harness
require_once __DIR__ . '/TestCase.php';

echo "========================================================\n";
echo " Running Content Intelligence Automated Test Suite\n";
echo "========================================================\n\n";

$testFiles = glob(__DIR__ . '/unit/*Test.php');
$totalTests = 0;
$totalAssertions = 0;
$passed = 0;
$failed = 0;

foreach ($testFiles as $file) {
    require_once $file;
    $className = 'abdulkadiragoliya\\contentintelligencetests\\unit\\' . basename($file, '.php');

    if (!class_exists($className)) {
        continue;
    }

    $ref = new ReflectionClass($className);
    $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
    $instance = new $className();

    echo "Running " . basename($file) . ":\n";

    foreach ($methods as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $totalTests++;
        try {
            $method->invoke($instance);
            echo "  [PASS] {$method->getName()}\n";
            $passed++;
        } catch (\Throwable $e) {
            echo "  [FAIL] {$method->getName()}: {$e->getMessage()}\n";
            $failed++;
        }
    }
    echo "\n";
}

echo "========================================================\n";
echo " Test Results: {$passed} Passed, {$failed} Failed (Total: {$totalTests})\n";
echo "========================================================\n";

exit($failed === 0 ? 0 : 1);
