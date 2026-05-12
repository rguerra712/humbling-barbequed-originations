<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/gemini.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$error = null;
$slugStep = false;
$slugChoices = [];
$pendingTitle = '';
$pendingBody = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? '';

    if ($step === 'choose_slug') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $chosenSlug = trim($_POST['chosen_slug'] ?? '');

        if ($title === '' || $body === '' || $chosenSlug === '') {
            $error = 'Title, body, and slug are required.';
        } else {
            $check = db()->prepare('SELECT id FROM documents WHERE slug = ?');
            $check->execute([$chosenSlug]);
            if ($check->fetch()) {
                $error = 'The selected slug was just claimed by another document. Please try creating the document again.';
            } else {
                $stmt = db()->prepare('INSERT INTO documents (title, body, slug, created_by) VALUES (?, ?, ?, ?)');
                $stmt->execute([$title, $body, $chosenSlug, $staff['id']]);
                $docId = (int) db()->lastInsertId();
                audit_log('create', 'document', $docId, ['title' => $title, 'slug' => $chosenSlug]);
                header('Location: /admin.php?created=' . $docId);
                exit;
            }
        }
    } else {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($title === '' || $body === '') {
            $error = 'Title and body are required.';
        } else {
            $slugChoices = gemini_suggest_slugs($title);
            $slugStep = true;
            $pendingTitle = $title;
            $pendingBody = $body;
        }
    }
}

$docs = db()->query('
    SELECT d.*, s.name AS creator_name
    FROM documents d
    JOIN staff s ON s.id = d.created_by
    ORDER BY d.created_at DESC
')->fetchAll();

render_header('Admin', $staff);
?>

<h1 class="page-title">Admin</h1>
<p class="page-subtitle">Create documents and generate share links for recipients.</p>

<?php if (!empty($_GET['created'])): ?>
    <div class="banner banner-success">Document #<?= (int) $_GET['created'] ?> created.</div>
<?php endif ?>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<?php if ($slugStep): ?>
<section class="card">
    <h2 class="card-title">Choose a slug for "<?= h($pendingTitle) ?>"</h2>
    <p>Select a human-readable ID for this document. This cannot be changed later.</p>
    <form method="post">
        <input type="hidden" name="step" value="choose_slug">
        <input type="hidden" name="title" value="<?= h($pendingTitle) ?>">
        <input type="hidden" name="body" value="<?= h($pendingBody) ?>">
        <div class="form-field">
            <?php foreach ($slugChoices as $i => $slug): ?>
                <label style="display:block;margin-bottom:0.5rem;">
                    <input type="radio" name="chosen_slug" value="<?= h($slug) ?>" <?= $i === 0 ? 'checked' : '' ?> required>
                    <?= h($slug) ?>
                </label>
            <?php endforeach ?>
        </div>
        <button type="submit" class="btn">Create document</button>
    </form>
</section>
<?php else: ?>
<section class="card">
    <h2 class="card-title">New document</h2>
    <form method="post">
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-field">
            <label for="body">Body</label>
            <textarea id="body" name="body" required></textarea>
        </div>
        <button type="submit" class="btn">Create document</button>
    </form>
</section>
<?php endif ?>

<form method="get" action="/results.php" class="search-form">
    <input type="search" name="q" placeholder="Search documents by title…" required>
    <button type="submit" class="btn">Search</button>
</form>

<section class="card">
    <h2 class="card-title">Documents</h2>
    <?php if (empty($docs)): ?>
        <p class="empty">No documents yet.</p>
    <?php else: ?>
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Slug</th>
                    <th>Title</th>
                    <th>Creator</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr>
                        <td class="id">#<?= (int) $d['id'] ?></td>
                        <td><?= $d['slug'] !== null ? h($d['slug']) : '<em>—</em>' ?></td>
                        <td><?= h($d['title']) ?></td>
                        <td><?= h($d['creator_name']) ?></td>
                        <td><?= h($d['created_at']) ?></td>
                        <td><a href="/share.php?doc=<?= (int) $d['id'] ?>" class="btn-link">Create share →</a></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>

<?php render_footer(); ?>
