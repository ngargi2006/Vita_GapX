#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

$testDir = __DIR__ . '/../tests';
$startTime = microtime(true);

$testFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testDir, FilesystemIterator::SKIP_DOTS)
);

$beforeClasses = get_declared_classes();
$classes = [];
foreach ($testFiles as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
        require_once $file->getPathname();
    }
}
$afterClasses = get_declared_classes();
$newClasses = array_diff($afterClasses, $beforeClasses);
foreach ($newClasses as $class) {
    if (is_subclass_of($class, VitaGap\Tests\TestCase::class)) {
        $reflection = new ReflectionClass($class);
        if (!$reflection->isAbstract()) {
            $classes[] = $class;
        }
    }
}

$passed = 0;
$failed = 0;
$errors = [];

echo "Running Vita-Gap Test Suite...\n\n";

foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    foreach ($methods as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $testCase = new $class();
        $testCase->setUp();
        
        try {
            $testCase->{$method->getName()}();
            $passed++;
            echo " \033[32m✔\033[0m {$class}::{$method->getName()}\n";
        } catch (Throwable $e) {
            $failed++;
            echo " \033[31m✖\033[0m {$class}::{$method->getName()}: {$e->getMessage()}\n";
            $errors[] = [
                'test' => "{$class}::{$method->getName()}",
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        } finally {
            $testCase->tearDown();
        }
    }
}

$elapsed = round((microtime(true) - $startTime) * 1000, 2);
echo "\n--------------------------------------------------\n";
echo "Tests: " . ($passed + $failed) . ", Passed: {$passed}, Failed: {$failed} ({$elapsed} ms)\n";

if ($failed > 0) {
    echo "\nFailures:\n";
    foreach ($errors as $err) {
        echo "- {$err['test']}: {$err['message']} ({$err['file']}:{$err['line']})\n";
    }
    exit(1);
}

echo "\033[32mAll tests passed successfully!\033[0m\n";
exit(0);
