<?php

require __DIR__ . '/lib/bootstrap.php';

$pdo = db();

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    filename TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
)");

$applied = $pdo->query("SELECT filename FROM schema_migrations ORDER BY filename")
               ->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        continue;
    }
    $pdo->exec(file_get_contents($file));
    $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)")->execute([$name]);
    echo "Applied: {$name}\n";
    $count++;
}

if ($count === 0) {
    echo "No new migrations.\n";
}
