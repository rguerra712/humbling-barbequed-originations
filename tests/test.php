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

// Helper: run the ranked search query directly against the DB
function search_docs(string $q): array {
    $stmt = db()->prepare('
        SELECT *,
          CASE
            WHEN lower(title) = lower(:q)           THEN 0
            WHEN lower(title) LIKE lower(:q) || \'%\' THEN 1
            ELSE                                         2
          END AS match_rank
        FROM documents
        WHERE lower(title) LIKE \'%\' || lower(:q) || \'%\'
        ORDER BY match_rank ASC, title ASC
        LIMIT 50
    ');
    $stmt->execute([':q' => $q]);
    return $stmt->fetchAll();
}

test('search exact match returns rank 0', function () {
    $rows = search_docs('Welcome Packet');
    assert_true(count($rows) >= 1, 'expected at least one result');
    assert_true((int) $rows[0]['match_rank'] === 0, 'expected rank 0 for exact match, got ' . $rows[0]['match_rank']);
    assert_true($rows[0]['title'] === 'Welcome Packet', 'unexpected title: ' . $rows[0]['title']);
});

test('search prefix match returns rank 1', function () {
    $rows = search_docs('Welcome');
    assert_true(count($rows) >= 1, 'expected at least one result');
    assert_true((int) $rows[0]['match_rank'] === 1, 'expected rank 1 for prefix match, got ' . $rows[0]['match_rank']);
});

test('search contains match returns rank 2', function () {
    $rows = search_docs('Packet');
    assert_true(count($rows) >= 1, 'expected at least one result for contains match');
    // "Welcome Packet" does not start with "Packet", so it should be rank 2
    assert_true((int) $rows[0]['match_rank'] === 2, 'expected rank 2 for contains-only match, got ' . $rows[0]['match_rank']);
});

test('search is case-insensitive', function () {
    $rows = search_docs('WELCOME PACKET');
    assert_true(count($rows) >= 1, 'expected result for uppercase query');
    assert_true((int) $rows[0]['match_rank'] === 0, 'expected rank 0 for case-insensitive exact match, got ' . $rows[0]['match_rank']);
});

test('search returns empty for no match', function () {
    $rows = search_docs('zzz_no_such_document_zzz');
    assert_true(count($rows) === 0, 'expected no results for unmatched query');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
