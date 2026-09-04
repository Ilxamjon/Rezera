<?php

$lines = file(__DIR__.'/../test-results-audit.txt', FILE_IGNORE_NEW_LINES);
$failed = [];
$name = null;
$buf = '';

foreach ($lines as $line) {
    if (str_contains($line, 'FAILED  Tests\\')) {
        if ($name !== null) {
            $failed[$name] = $buf;
        }
        $name = trim(substr($line, strpos($line, 'FAILED  Tests\\') + 7));
        $buf = '';
    } elseif ($name !== null) {
        $buf .= $line."\n";
    }
}

if ($name !== null) {
    $failed[$name] = $buf;
}

$a = $b = $c = 0;

foreach ($failed as $body) {
    if (preg_match('/08006|no password supplied|fe_sendauth|password authentication failed/i', $body)) {
        $a++;
    } elseif (preg_match('/UniqueConstraintViolation|duplicate key value violates unique constraint|23514|Check violation|23P01|exclusion constraint|QueryException/i', $body)) {
        $b++;
    } elseif (preg_match('/Expected response status code|Failed asserting|assertJson/i', $body)) {
        $c++;
    } else {
        $c++;
    }
}

echo 'Failed tests parsed: '.count($failed).PHP_EOL;
echo 'A environment/config: '.$a.PHP_EOL;
echo 'B backend bugs or DB constraint/data issues: '.$b.PHP_EOL;
echo 'C assertion or other test expectation failures: '.$c.PHP_EOL;
