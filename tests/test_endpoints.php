<?php
/**
 * Automated PHP CLI Test Suite
 * Tests Model operations, Router pattern matching, and API responses.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../routes/Router.php';

$totalAssertions = 0;
$passedAssertions = 0;

function assertTest(bool $condition, string $message): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
    }
}

echo "=== Istanbul University MIS Alumni Test Suite ===\n\n";

// Group 1: User Model Operations
echo "Test Group 1: User Domain Model Operations\n";
User::reset();
$initialCount = User::count();
assertTest($initialCount >= 3, "Initial seed users loaded in model (count: {$initialCount})");

$newUser = User::create([
    'student_id' => '130120099',
    'first_name' => 'Kerem',
    'last_name' => 'Aktas',
    'email' => 'kerem.aktas@ogr.iu.edu.tr',
    'role' => 'alumni',
    'graduation_year' => 2023,
    'company' => 'Aselsan',
    'job_title' => 'Systems Analyst'
]);
assertTest($newUser['id'] > 0, "New user created with valid ID");
assertTest(User::count() === $initialCount + 1, "User::count() incremented correctly");

$found = User::findById($newUser['id']);
assertTest($found !== null && $found['email'] === 'kerem.aktas@ogr.iu.edu.tr', "User::findById() retrieved correct record");

$updated = User::update($newUser['id'], ['company' => 'Havelsan']);
assertTest($updated['company'] === 'Havelsan', "User::update() modified company correctly");

$deleted = User::delete($newUser['id']);
assertTest($deleted === true, "User::delete() returned true");
assertTest(User::findById($newUser['id']) === null, "Deleted user is no longer retrievable");

// Group 2: Router Engine Verification
echo "\nTest Group 2: Router Engine Pattern Matching\n";
$router = new Router();
$matchedHello = false;
$receivedName = '';

$router->get('/auto/hello/{name}', function ($name) use (&$matchedHello, &$receivedName) {
    $matchedHello = true;
    $receivedName = $name;
});

$result = $router->dispatch('GET', '/auto/hello/Istanbul');
assertTest($result === true, "Router matched GET /auto/hello/{name}");
assertTest($matchedHello === true && $receivedName === 'Istanbul', "Extracted route parameter '{name}' correctly as 'Istanbul'");

$sumA = 0;
$sumB = 0;
$router->get('/auto/sum/{a}/{b}', function ($a, $b) use (&$sumA, &$sumB) {
    $sumA = (int)$a;
    $sumB = (int)$b;
});
$resultSum = $router->dispatch('GET', '/auto/sum/25/75');
assertTest($resultSum === true, "Router matched parameterized route /auto/sum/{a}/{b}");
assertTest(($sumA + $sumB) === 100, "Calculated route sum parameter correctly (25 + 75 = 100)");

echo "\n==================================================\n";
echo "Summary: {$passedAssertions} of {$totalAssertions} assertions passed.\n";

if ($passedAssertions === $totalAssertions) {
    echo "Result: ALL TESTS PASSED! [OK]\n";
    exit(0);
} else {
    echo "Result: SOME TESTS FAILED!\n";
    exit(1);
}
