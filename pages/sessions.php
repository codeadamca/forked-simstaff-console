<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$conn = getConnection();

$filterEventId = isset($_GET['event_id']) && (int) $_GET['event_id'] > 0
    ? (int) $_GET['event_id']
    : 0;

$allEvents = $conn->query('
    SELECT event_id, event_name
    FROM   events
    ORDER  BY event_date DESC
')->fetch_all(MYSQLI_ASSOC);

if ($filterEventId > 0) {
    $stmt = $conn->prepare('
        SELECT s.session_id,
               s.participant_name,
               s.best_lap_time,
               s.created_at,
               e.event_name,
               e.event_id
        FROM   sessions s
        LEFT JOIN events e ON e.event_id = s.event_id
        WHERE  s.event_id = ?
        ORDER  BY s.created_at DESC
    ');
    $stmt->bind_param('i', $filterEventId);
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $sessions = $conn->query('
        SELECT s.session_id,
               s.participant_name,
               s.best_lap_time,
               s.created_at,
               e.event_name,
               e.event_id
        FROM   sessions s
        LEFT JOIN events e ON e.event_id = s.event_id
        ORDER  BY s.created_at DESC
    ')->fetch_all(MYSQLI_ASSOC);
}

$conn->close();

$activeEventName = 'All Events';
if ($filterEventId > 0) {
    foreach ($allEvents as $ev) {
        if ((int) $ev['event_id'] === $filterEventId) {
            $activeEventName = $ev['event_name'];
            break;
        }
    }
}

$pageTitle = 'Sessions';
include __DIR__ . '/../includes/header.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>

<?php if (isset($_SESSION['flash'])): ?>
    <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
    <div class="alert alert-<?= h($flash['type']) ?> mb-4">
        <?= h($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2>Sessions — <?= h($activeEventName) ?></h2>
    <a href="manage_events.php" class="btn btn-secondary">← Back to Events</a>
</div>

<!-- Filter Bar -->
<form method="GET" class="d-flex align-items-center gap-2 mb-4">
    <label for="event_id" class="form-label mb-0">Filter by Event</label>
    <select name="event_id" id="event_id" class="form-select w-auto">
        <option value="0">All Events</option>
        <?php foreach ($allEvents as $ev): ?>
            <option value="<?= (int) $ev['event_id'] ?>" <?= (int) $ev['event_id'] === $filterEventId ? 'selected' : '' ?>>
                <?= h($ev['event_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Apply</button>
    <?php if ($filterEventId > 0): ?>
        <a href="sessions.php" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
</form>

<!-- Sessions Table -->
<div class="card">
    <?php if (empty($sessions)): ?>
        <p class="empty-state">No sessions found for this event.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-borderless mb-0">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Participant</th>
                        <th>Best Lap</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td>
                                <a href="sessions.php?event_id=<?= (int) $s['event_id'] ?>" class="table-link">
                                    <?= h($s['event_name'] ?? '—') ?>
                                </a>
                            </td>
                            <td><?= h($s['participant_name'] ?? '—') ?></td>
                            <td><strong><?= $s['best_lap_time'] !== '' ? h($s['best_lap_time']) : '—' ?></strong></td>
                            <td><?= $s['created_at'] ? date('M j, Y', strtotime($s['created_at'])) : '—' ?></td>
                            <td>
                                <form method="POST" action="session_delete.php"
                                      onsubmit="return confirm('Delete this session?')">
                                    <input type="hidden" name="session_id" value="<?= (int) $s['session_id'] ?>">
                                    <input type="hidden" name="redirect" value="sessions.php?event_id=<?= $filterEventId ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
