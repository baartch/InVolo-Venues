<?php
/**
 * CSRF Token Test Script
 * 
 * This script tests the CSRF protection implementation.
 * Run this from the command line: php tests/csrf_test.php
 */

require_once __DIR__ . '/../app/src-php/auth/csrf.php';

echo "CSRF Token Protection Test\n";
echo "==========================\n\n";

// Test 1: Generate token
echo "Test 1: Generating CSRF token...\n";
$token1 = generateCsrfToken();
echo "✓ Token generated: " . substr($token1, 0, 16) . "...\n";
echo "  Length: " . strlen($token1) . " characters\n\n";

// Test 2: Get token (should return same token)
echo "Test 2: Getting existing token...\n";
$token2 = getCsrfToken();
if ($token1 === $token2) {
    echo "✓ Same token returned\n\n";
} else {
    echo "✗ Different token returned (FAILED)\n\n";
}

// Test 3: Validate correct token
echo "Test 3: Validating correct token...\n";
if (validateCsrfToken($token1)) {
    echo "✓ Token validated successfully\n\n";
} else {
    echo "✗ Token validation failed (FAILED)\n\n";
}

// Test 4: Validate incorrect token
echo "Test 4: Validating incorrect token...\n";
$badToken = bin2hex(random_bytes(32));
if (!validateCsrfToken($badToken)) {
    echo "✓ Invalid token correctly rejected\n\n";
} else {
    echo "✗ Invalid token accepted (FAILED)\n\n";
}

// Test 5: Validate empty token
echo "Test 5: Validating empty token...\n";
if (!validateCsrfToken('')) {
    echo "✓ Empty token correctly rejected\n\n";
} else {
    echo "✗ Empty token accepted (FAILED)\n\n";
}

// Test 6: Token expiration (simulate by manipulating session time)
echo "Test 6: Testing token expiration...\n";
$_SESSION['csrf_token_time'] = time() - 7201; // Set to 2 hours + 1 second ago
if (!validateCsrfToken($token1)) {
    echo "✓ Expired token correctly rejected\n\n";
} else {
    echo "✗ Expired token accepted (FAILED)\n\n";
}

// Test 7: Generate new token after expiration
echo "Test 7: Generating new token after expiration...\n";
$token3 = getCsrfToken();
if ($token3 !== $token1) {
    echo "✓ New token generated after expiration\n";
} else {
    echo "✗ Same token returned (FAILED)\n";
}
if (validateCsrfToken($token3)) {
    echo "✓ New token validates successfully\n\n";
} else {
    echo "✗ New token validation failed (FAILED)\n\n";
}

echo "==========================\n";
echo "All tests completed!\n";
