<?php

require __DIR__ . '/../lib/bootstrap.php';

system('php ' . escapeshellarg(__DIR__ . '/../seed.php') . ' > /dev/null', $rc);
if ($rc !== 0) {
    fwrite(STDERR, "seed failed\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;
    try {
        $fn();
        echo "  [ok] {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

function assert_true($cond, string $msg = ''): void {
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected true');
    }
}

echo "\nRunning tests:\n";

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title: ' . var_export($row['title'], true));
});

test('migrate.php creates schema_migrations table and records applied files', function () {
    $migrationsDir = __DIR__ . '/../migrations';
    $tmpFile = $migrationsDir . '/999_test_migration.sql';
    file_put_contents($tmpFile, "CREATE TABLE IF NOT EXISTS _migration_test (id INTEGER PRIMARY KEY);");

    try {
        system('php ' . escapeshellarg(__DIR__ . '/../migrate.php') . ' > /dev/null', $rc);
        assert_true($rc === 0, 'migrate.php exited non-zero');

        $row = db()->query("SELECT filename FROM schema_migrations WHERE filename = '999_test_migration.sql'")->fetch();
        assert_true($row !== false, 'migration file not recorded in schema_migrations');

        $tables = db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='_migration_test'")->fetch();
        assert_true($tables !== false, '_migration_test table not created by migration');

        // Running again is idempotent
        system('php ' . escapeshellarg(__DIR__ . '/../migrate.php') . ' > /dev/null', $rc2);
        assert_true($rc2 === 0, 'second migrate.php run exited non-zero');
        $count = db()->query("SELECT COUNT(*) FROM schema_migrations WHERE filename = '999_test_migration.sql'")->fetchColumn();
        assert_true((int)$count === 1, 'migration recorded more than once');
    } finally {
        unlink($tmpFile);
    }
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
