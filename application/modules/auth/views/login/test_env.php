<?php
// Simple test to check environment variable
echo "<h2>Environment Variable Test</h2>";

$envVar = getenv('ALLOW_ALTERNATIVE_LOGIN');
echo "<p><strong>Raw getenv('ALLOW_ALTERNATIVE_LOGIN'):</strong> " . var_export($envVar, true) . "</p>";

if ($envVar !== false && $envVar !== null) {
    $converted = in_array(strtolower(trim($envVar)), ['true', '1', 'yes', 'on']);
    echo "<p><strong>Converted to boolean:</strong> " . var_export($converted, true) . "</p>";
} else {
    echo "<p><strong>Using default (true):</strong> " . var_export(true, true) . "</p>";
}

echo "<p><strong>All environment variables containing 'LOGIN':</strong></p>";
echo "<pre>";
foreach ($_ENV as $key => $value) {
    if (stripos($key, 'LOGIN') !== false) {
        echo "$key = $value\n";
    }
}
echo "</pre>";

echo "<p><strong>All environment variables containing 'ALLOW':</strong></p>";
echo "<pre>";
foreach ($_ENV as $key => $value) {
    if (stripos($key, 'ALLOW') !== false) {
        echo "$key = $value\n";
    }
}
echo "</pre>";
?>
