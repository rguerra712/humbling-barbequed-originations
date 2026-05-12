<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$q = trim($_GET['q'] ?? '');

$results = [];
if ($q !== '') {
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
    $results = $stmt->fetchAll();
}

$tiers = [
    0 => ['label' => 'Exact match',   'rows' => []],
    1 => ['label' => 'Starts with',   'rows' => []],
    2 => ['label' => 'Other matches', 'rows' => []],
];
foreach ($results as $row) {
    $tiers[(int) $row['match_rank']]['rows'][] = $row;
}

render_header('Search results', $staff);
?>

<a href="/admin.php" class="back-link">← back to admin</a>

<div class="page-header-row">
    <h1 class="page-title">
        <?php if ($q !== ''): ?>
            Results for "<?= h($q) ?>"
        <?php else: ?>
            Search documents
        <?php endif ?>
    </h1>
    <form method="get" action="/results.php" class="search-form">
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search documents…" required>
        <button type="submit" class="btn">Search</button>
    </form>
</div>

<?php if ($q === ''): ?>
    <p class="empty">Enter a search term above.</p>
<?php elseif (empty($results)): ?>
    <p class="empty">No results for "<?= h($q) ?>".</p>
<?php else: ?>
    <?php foreach ($tiers as $tier): ?>
        <?php if (!empty($tier['rows'])): ?>
            <section class="card">
                <h2 class="card-title"><?= h($tier['label']) ?></h2>
                <table class="data">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tier['rows'] as $d): ?>
                            <tr>
                                <td class="id">#<?= (int) $d['id'] ?></td>
                                <td><?= h($d['title']) ?></td>
                                <td><?= h($d['created_at']) ?></td>
                                <td><a href="/share.php?doc=<?= (int) $d['id'] ?>" class="btn-link">Create share →</a></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </section>
        <?php endif ?>
    <?php endforeach ?>
<?php endif ?>

<?php render_footer(); ?>
