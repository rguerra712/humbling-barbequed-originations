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

test('share with past publish_at allows body to be shown', function () {
    $pastTime = date('Y-m-d H:i:s', time() - 3600);
    $token = random_token();
    $stmt = db()->prepare('INSERT INTO shares (document_id, token, recipient_email, publish_at) VALUES (1, ?, ?, ?)');
    $stmt->execute([$token, 'past@example.com', $pastTime]);

    $stmt = db()->prepare('SELECT d.*, s.publish_at FROM shares s JOIN documents d ON d.id = s.document_id WHERE s.token = ?');
    $stmt->execute([$token]);
    $doc = $stmt->fetch();

    assert_true($doc !== false, 'share not found');
    $embargoed = $doc['publish_at'] !== null && time() < strtotime($doc['publish_at']);
    assert_true(!$embargoed, 'expected body to be visible for past publish_at');
});

test('share with future publish_at withholds body and retains title', function () {
    $futureTime = date('Y-m-d H:i:s', time() + 3600);
    $token = random_token();
    $stmt = db()->prepare('INSERT INTO shares (document_id, token, recipient_email, publish_at) VALUES (1, ?, ?, ?)');
    $stmt->execute([$token, 'future@example.com', $futureTime]);

    $stmt = db()->prepare('SELECT d.title, d.body, s.publish_at FROM shares s JOIN documents d ON d.id = s.document_id WHERE s.token = ?');
    $stmt->execute([$token]);
    $doc = $stmt->fetch();

    assert_true($doc !== false, 'share not found');
    $embargoed = $doc['publish_at'] !== null && time() < strtotime($doc['publish_at']);
    assert_true($embargoed, 'expected body to be withheld for future publish_at');
    assert_true($doc['title'] !== '', 'title should still be accessible');
});

// --- TASK-6: Human-readable document IDs ---

require_once __DIR__ . '/../lib/gemini.php';

test('looks_like_pii() detects email address in title', function () {
    assert_true(looks_like_pii('Contact admin@example.com for access'), 'expected email to be flagged as PII');
});

test('looks_like_pii() detects US phone number in title', function () {
    assert_true(looks_like_pii('Call 555-123-4567 for support'), 'expected phone to be flagged as PII');
});

test('looks_like_pii() detects SSN in title', function () {
    assert_true(looks_like_pii('Employee SSN is 123-45-6789'), 'expected SSN to be flagged as PII');
});

test('looks_like_pii() returns false for clean title', function () {
    assert_true(!looks_like_pii('Q3 Revenue Report'), 'expected clean title to not be flagged');
    assert_true(!looks_like_pii('Welcome Packet'), 'expected Welcome Packet to not be flagged');
});

test('seeded document has a non-null slug', function () {
    $row = db()->query("SELECT slug FROM documents WHERE id = 1")->fetch();
    assert_true($row !== false, 'document id=1 not found');
    assert_true($row['slug'] !== null && $row['slug'] !== '', 'expected non-empty slug on seeded document');
    assert_true($row['slug'] === 'welcome-packet', 'expected slug to be welcome-packet, got: ' . var_export($row['slug'], true));
});

test('slug uniqueness constraint prevents duplicate slugs', function () {
    $thrown = false;
    try {
        db()->prepare('INSERT INTO documents (title, body, slug, created_by) VALUES (?, ?, ?, 1)')
            ->execute(['Duplicate', 'Body text', 'welcome-packet']);
    } catch (PDOException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'expected UNIQUE constraint violation for duplicate slug');
});

test('_slug_fallback generates URL-safe slug from title', function () {
    // Insert a doc with slug 'test-doc' first to ensure no collision on the base
    db()->prepare('INSERT INTO documents (title, body, slug, created_by) VALUES (?, ?, ?, 1)')
        ->execute(['Test Doc Source', 'body', 'test-doc-source']);
    $slug = _slug_fallback('Test Doc Source !!');
    assert_true(preg_match('/^[a-z0-9-]+$/', $slug) === 1, 'slug should be URL-safe, got: ' . $slug);
    assert_true($slug !== '', 'slug should not be empty');
    assert_true(!_slug_exists($slug), 'fallback slug must not already exist in DB');
});

test('_slug_fallback appends numeric suffix when base slug is taken', function () {
    // welcome-packet is taken by the seeded document
    $slug = _slug_fallback('Welcome Packet');
    assert_true($slug !== 'welcome-packet', 'expected suffix since welcome-packet is already taken');
    assert_true(preg_match('/^welcome-packet-\d{4}$/', $slug) === 1, 'expected welcome-packet-NNNN format, got: ' . $slug);
});

test('gemini_suggest_slugs returns at least one URL-safe slug (fallback when no API key)', function () {
    $original = getenv('GEMINI_API_KEY');
    putenv('GEMINI_API_KEY=');
    try {
        $slugs = gemini_suggest_slugs('My Test Document Title');
        assert_true(count($slugs) >= 1, 'expected at least one slug');
        assert_true($slugs[0] !== '', 'expected non-empty slug');
        assert_true(preg_match('/^[a-z0-9-]+$/', $slugs[0]) === 1, 'slug must be URL-safe, got: ' . $slugs[0]);
    } finally {
        if ($original !== false && $original !== '') {
            putenv("GEMINI_API_KEY={$original}");
        }
    }
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
