<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$conn = getConnection();

$sessions = $conn->query('
    SELECT s.session_id,
           s.participant_name,
           s.best_lap_time,
           s.created_at,
           e.event_name,
           e.event_id
    FROM sessions s
    LEFT JOIN events e ON e.event_id = s.event_id
    ORDER BY s.created_at DESC
')->fetch_all(MYSQLI_ASSOC);

$conn->close();

$flash = getFlash();
$pageTitle = 'All Sessions';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2> All Sessions</h2>
</div>

<?php if ($flash): ?>
    <p class="alert alert--<?= $flash['type'] ?>"><?= $flash['message'] ?></p>
<?php endif; ?>

<?php if (empty($sessions)): ?>
    <p class="empty-state">No sessions recorded yet.</p>
<?php else: ?>
<div class="table-container">
    <table class="table">
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
                        <a href="event_detail.php?id=<?= $s['event_id'] ?>">
                            <?= htmlspecialchars($s['event_name'] ?? '—') ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($s['participant_name'] ?? '—') ?></td>
                    <td><strong><?= $s['best_lap_time'] !== '' ? htmlspecialchars($s['best_lap_time']) : '—' ?></strong></td>
                    <td><?= $s['created_at'] ? date('M j, Y', strtotime($s['created_at'])) : '—' ?></td>
                    <td class="actions">
                        <form method="POST" action="session_delete.php" style="display:inline">
                            <input type="hidden" name="session_id" value="<?= $s['session_id'] ?>">
                            <input type="hidden" name="from"       value="sessions">
                            <button type="submit" class="btn btn--danger btn--sm"
                                    onclick="return confirm('Delete this session?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
