<?php

declare(strict_types=1);

$path = $argv[1] ?? __DIR__.'/../test-triage-output.txt';
$content = file_get_contents($path);

preg_match('/Tests:\s+(\d+) failed(?:,\s+(\d+) skipped)?(?:,\s+(\d+) passed)?|Tests:\s+(\d+) passed(?:,\s+(\d+) failed)?/i', $content, $summary);

$failed = 0;
$passed = 0;
$skipped = 0;

if (preg_match('/Tests:\s+(\d+) failed,\s+(\d+) passed/', $content, $m)) {
    $failed = (int) $m[1];
    $passed = (int) $m[2];
} elseif (preg_match('/Tests:\s+(\d+) passed,\s+(\d+) failed/', $content, $m)) {
    $passed = (int) $m[1];
    $failed = (int) $m[2];
}

if (preg_match('/,\s+(\d+) skipped/', $content, $m)) {
    $skipped = (int) $m[1];
}

$total = $failed + $passed + $skipped;

echo "TOTAL: {$total}\n";
echo "PASSED: {$passed}\n";
echo "FAILED: {$failed}\n";
echo "SKIPPED: {$skipped}\n";
echo "ERRORS: 0 (PHPUnit reports failures, not separate error count)\n\n";

$blocks = preg_split('/\n\s+FAILED\s+Tests\\\\Feature\\\\|\n\s+FAILED\s+Tests\\\\Unit\\\\/', $content);
array_shift($blocks);

$groups = [];

foreach ($blocks as $block) {
    if (! preg_match('/^([^\n]+)/', $block, $nameMatch)) {
        continue;
    }
    $name = 'Tests\\Feature\\'.$nameMatch[1];
    if (str_contains($block, 'Tests\\Unit\\')) {
        $name = preg_replace('/^([^\n]+)/', 'Tests\\Unit\\$1', $nameMatch[1]) ?? $nameMatch[1];
    }

    $category = 'I. Other';
    $root = 'Unclassified';

    if (preg_match('/08006|no password supplied|fe_sendauth|password authentication failed/i', $block)) {
        $category = 'A. Environment / database configuration';
        $root = 'PostgreSQL auth/config';
    } elseif (preg_match('/business_members_business_id_user_id_unique|UniqueConstraintViolationException.*business_members/', $block)) {
        $category = 'B. Test bootstrap problems';
        $root = 'Duplicate business_members in test setup';
    } elseif (preg_match('/business_hours_schedule_check/', $block)) {
        $category = 'C. Broken or outdated tests';
        $root = 'Test/factory creates invalid business_hours rows';
    } elseif (preg_match('/reservations_duration_matches_check/', $block)) {
        $category = 'H. Reservation / availability problems';
        $root = 'Reservation duration_minutes mismatch with starts_at/ends_at';
    } elseif (preg_match('/23P01|exclusion constraint|reservations_no_overlap/i', $block)) {
        $category = 'H. Reservation / availability problems';
        $root = 'Reservation overlap / exclusion constraint';
    } elseif (preg_match('/Expected response status code \[403\].*received 200|admin api/i', $block)) {
        $category = 'G. Multi-tenancy / IDOR problems';
        $root = 'Admin/authorization returns 200 instead of 403';
    } elseif (preg_match('/Expected response status code \[403\]|Expected response status code \[404\].*received 200/i', $block)) {
        $category = 'F. Authentication / authorization problems';
        $root = 'Missing or incorrect authorization gate';
    } elseif (preg_match('/Expected response status code/', $block)) {
        $category = 'D. Actual backend bugs';
        $root = 'API response mismatch (status/body)';
    } elseif (preg_match('/QueryException|23514|Check violation/', $block)) {
        $category = 'D. Actual backend bugs';
        $root = 'Database CHECK constraint violated by application/test data';
    } elseif (preg_match('/assertJsonValidationErrors|validation/i', $block)) {
        $category = 'C. Broken or outdated tests';
        $root = 'Validation expectation mismatch';
    }

    if (! isset($groups[$root])) {
        $groups[$root] = ['category' => $category, 'count' => 0, 'tests' => []];
    }
    $groups[$root]['count']++;
    $groups[$root]['tests'][] = trim($nameMatch[1]);
}

uasort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

echo "ROOT CAUSE GROUPS\n";
echo str_repeat('-', 80)."\n";
foreach ($groups as $root => $data) {
    echo sprintf("[%s] %s — %d tests\n", $data['category'], $root, $data['count']);
    foreach (array_slice($data['tests'], 0, 3) as $t) {
        echo "  - {$t}\n";
    }
    if ($data['count'] > 3) {
        echo '  - ...'.($data['count'] - 3)." more\n";
    }
    echo "\n";
}
