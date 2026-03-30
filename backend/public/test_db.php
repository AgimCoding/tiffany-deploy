<?php
// Parse .env.local
$envFile = __DIR__ . '/../.env.local';
if (!file_exists($envFile)) {
    die('.env.local not found at: ' . $envFile);
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$dbUrl = null;
foreach ($lines as $line) {
    if (str_starts_with($line, '#')) continue;
    if (str_contains($line, 'DATABASE_URL')) {
        $dbUrl = trim(explode('=', $line, 2)[1], "' \"");
        break;
    }
}

if (!$dbUrl) {
    die('DATABASE_URL not found in .env.local');
}

echo "DATABASE_URL found: " . preg_replace('/:[^@]+@/', ':***@', $dbUrl) . "\n<br><br>";

// Parse URL
$parsed = parse_url($dbUrl);
$host = $parsed['host'] ?? 'unknown';
$port = $parsed['port'] ?? 3306;
$user = $parsed['user'] ?? 'unknown';
$pass = $parsed['pass'] ?? '';
$dbname = ltrim($parsed['path'] ?? '', '/');
$dbname = explode('?', $dbname)[0];

echo "Host: $host<br>";
echo "Port: $port<br>";
echo "User: $user<br>";
echo "Database: $dbname<br><br>";

// Test connection
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<b style='color:green'>CONNEXION OK</b><br><br>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables (" . count($tables) . "):<br>";
    foreach ($tables as $t) {
        echo "- $t<br>";
    }
} catch (PDOException $e) {
    echo "<b style='color:red'>ERREUR CONNEXION:</b><br>";
    echo $e->getMessage();
}
