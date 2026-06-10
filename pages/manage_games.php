<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add new game version
    if ($action === 'add_version') {
        $name = trim($_POST['version_name'] ?? '');
        if ($name) {
            $stmt = $conn->prepare('INSERT IGNORE INTO game_versions (name) VALUES (?)');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->close();
            setFlash('success', "Version \"$name\" added.");
        }

    // Delete a version (cascades to tracks/cars/racers)
    } elseif ($action === 'delete_version') {
        $id = (int) ($_POST['version_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM game_versions WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Version and all its data deleted.');
        }

    // Bulk add tracks / cars / racers
    } elseif ($action === 'bulk_add') {
        $versionId = (int) ($_POST['version_id'] ?? 0);
        $type      = $_POST['item_type'] ?? '';
        $raw       = trim($_POST['items'] ?? '');

        $allowed = ['game_tracks', 'game_cars', 'game_racers'];
        $table   = 'game_' . $type;

        if ($versionId > 0 && in_array($table, $allowed) && $raw !== '') {
            $items = preg_split('/[\n,]+/', $raw);
            $stmt  = $conn->prepare("INSERT IGNORE INTO `$table` (version_id, name) VALUES (?, ?)");
            $count = 0;
            foreach ($items as $item) {
                $item = trim($item);
                if ($item !== '') {
                    $stmt->bind_param('is', $versionId, $item);
                    $stmt->execute();
                    $count++;
                }
            }
            $stmt->close();
            setFlash('success', "$count item(s) added.");
        }

    // Delete single item from tracks/cars/racers
    } elseif ($action === 'delete_item') {
        $id    = (int) ($_POST['item_id']    ?? 0);
        $table = $_POST['item_table'] ?? '';

        $allowed = ['game_tracks', 'game_cars', 'game_racers'];
        if ($id > 0 && in_array($table, $allowed)) {
            $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Item deleted.');
        }
    }

    $conn->close();
    header('Location: manage_games.php');  // ← fixed
    exit();
}

// ── Load versions ─────────────────────────────────────────
$versions = $conn->query('SELECT * FROM game_versions ORDER BY name ASC')
                 ->fetch_all(MYSQLI_ASSOC);

$activeVersionId = (int) ($_GET['version_id'] ?? ($versions[0]['id'] ?? 0));

// ── Load items for active version ─────────────────────────
$tracks = $cars = $racers = [];
if ($activeVersionId > 0) {
    $stmt = $conn->prepare('SELECT * FROM game_tracks  WHERE version_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $activeVersionId);
    $stmt->execute();
    $tracks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT * FROM game_cars    WHERE version_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $activeVersionId);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT * FROM game_racers  WHERE version_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $activeVersionId);
    $stmt->execute();
    $racers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();

$flash     = getFlash();
$pageTitle = 'Manage Game';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2>Manage Game</h2>
    <a href="dashboard.php" class="btn btn--outline">← Back to Dashboard</a>
</div>

<?php if ($flash): ?>
    <p class="alert alert--<?= $flash['type'] ?>"><?= $flash['message'] ?></p>
<?php endif; ?>

<!-- Version Bar -->
<div class="version-bar">
    <div class="version-bar__left">
        <label>Game Version</label>
        <div class="version-bar__select-wrap">
            <select id="versionSelect" onchange="switchVersion(this.value)">
                <?php if (empty($versions)): ?>
                    <option disabled>No versions yet</option>
                <?php else: ?>
                    <?php foreach ($versions as $v): ?>
                        <option value="<?= $v['id'] ?>"
                            <?= $v['id'] == $activeVersionId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <?php if ($activeVersionId > 0): ?>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Delete this version and ALL its tracks, cars and racers?')">
                    <input type="hidden" name="action"     value="delete_version">
                    <input type="hidden" name="version_id" value="<?= $activeVersionId ?>">
                    <button type="submit" class="btn btn--danger btn--sm">🗑 Delete Version</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" class="version-bar__add">
        <input type="hidden" name="action" value="add_version">
        <input type="text"   name="version_name" placeholder="New version name..." required>
        <button type="submit" class="btn btn--primary btn--sm">+ Add Version</button>
    </form>
</div>

<?php if ($activeVersionId > 0): ?>

<div class="manage-grid">

    <!-- TRACKS -->
    <div class="manage-card">
        <div class="manage-card__header">🏁 Tracks</div>
        <form method="POST" class="manage-card__bulk-form">
            <input type="hidden" name="action"     value="bulk_add">
            <input type="hidden" name="version_id" value="<?= $activeVersionId ?>">
            <input type="hidden" name="item_type"  value="tracks">
            <textarea name="items" rows="3"
                placeholder="One per line or comma separated&#10;e.g. Monza&#10;Silverstone&#10;Spa"></textarea>
            <button type="submit" class="btn btn--primary btn--sm">+ Add</button>
        </form>
        <ul class="manage-card__list">
            <?php if (empty($tracks)): ?>
                <li class="manage-card__empty">No tracks yet.</li>
            <?php else: ?>
                <?php foreach ($tracks as $item): ?>
                    <li>
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <form method="POST">
                            <input type="hidden" name="action"     value="delete_item">
                            <input type="hidden" name="item_id"    value="<?= $item['id'] ?>">
                            <input type="hidden" name="item_table" value="game_tracks">
                            <button type="submit" class="btn--icon" title="Delete">✕</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- CARS -->
    <div class="manage-card">
        <div class="manage-card__header">🚗 Cars</div>
        <form method="POST" class="manage-card__bulk-form">
            <input type="hidden" name="action"     value="bulk_add">
            <input type="hidden" name="version_id" value="<?= $activeVersionId ?>">
            <input type="hidden" name="item_type"  value="cars">
            <textarea name="items" rows="3"
                placeholder="One per line or comma separated&#10;e.g. Ferrari SF-24&#10;Red Bull RB20"></textarea>
            <button type="submit" class="btn btn--primary btn--sm">+ Add</button>
        </form>
        <ul class="manage-card__list">
            <?php if (empty($cars)): ?>
                <li class="manage-card__empty">No cars yet.</li>
            <?php else: ?>
                <?php foreach ($cars as $item): ?>
                    <li>
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <form method="POST">
                            <input type="hidden" name="action"     value="delete_item">
                            <input type="hidden" name="item_id"    value="<?= $item['id'] ?>">
                            <input type="hidden" name="item_table" value="game_cars">
                            <button type="submit" class="btn--icon" title="Delete">✕</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- RACERS -->
    <div class="manage-card">
        <div class="manage-card__header">🧑‍✈️ Racers</div>
        <form method="POST" class="manage-card__bulk-form">
            <input type="hidden" name="action"     value="bulk_add">
            <input type="hidden" name="version_id" value="<?= $activeVersionId ?>">
            <input type="hidden" name="item_type"  value="racers">
            <textarea name="items" rows="3"
                placeholder="One per line or comma separated&#10;e.g. Leclerc&#10;Verstappen&#10;Hamilton"></textarea>
            <button type="submit" class="btn btn--primary btn--sm">+ Add</button>
        </form>
        <ul class="manage-card__list">
            <?php if (empty($racers)): ?>
                <li class="manage-card__empty">No racers yet.</li>
            <?php else: ?>
                <?php foreach ($racers as $item): ?>
                    <li>
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <form method="POST">
                            <input type="hidden" name="action"     value="delete_item">
                            <input type="hidden" name="item_id"    value="<?= $item['id'] ?>">
                            <input type="hidden" name="item_table" value="game_racers">
                            <button type="submit" class="btn--icon" title="Delete">✕</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

</div>

<?php else: ?>
    <p class="empty-state">No versions yet. Add one above to get started.</p>
<?php endif; ?>

<script>
function switchVersion(id) {
    window.location.href = 'manage_games.php?version_id=' + id;  // ← fixed
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
