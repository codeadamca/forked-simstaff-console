<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$conn = getConnection();
requireLogin();

// ── Events ────────────────────────────────────────────────────────────────────
$events = $conn->query('
    SELECT e.*,
           COUNT(s.session_id) AS session_count,
           gv.name             AS version_name
    FROM   events e
    LEFT JOIN sessions      s  ON s.event_id  = e.event_id
    LEFT JOIN game_versions gv ON gv.id        = e.version_id
    GROUP BY e.event_id
    ORDER BY e.event_date DESC
')->fetch_all(MYSQLI_ASSOC);

// ── Recent Laps ───────────────────────────────────────────────────────────────
$recentLaps = $conn->query('
    SELECT l.lap_number, l.lap_time, l.lap_time_ms,
           s.session_id,
           e.event_name,
           e.racer,
           e.track
    FROM   laps     l
    JOIN   sessions s ON s.session_id = l.session_id
    JOIN   events   e ON e.event_id   = s.event_id
    ORDER BY l.id DESC
    LIMIT 5
')->fetch_all(MYSQLI_ASSOC);

$conn->close();

$flash     = getFlash();
$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — F1 Lap Simulator</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container">
    <h1 class="page-title">Dashboard</h1>

    <?php if ($flash): ?>
        <div class="flash flash--<?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Events Table -->
    <section class="card">
        <div class="card-header">
            <h2>Events</h2>
            <a href="event_form.php" class="btn btn--primary btn--sm">+ New Event</a>
        </div>

        <?php if (empty($events)): ?>
            <p class="empty-state">No events yet. Create one to get started.</p>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Version</th>
                        <th>Track</th>
                        <th>Racer</th>
                        <th>Sessions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $event):
                    [$statusKey, $statusLabel, $statusClass] = resolveEventStatus(
                        $event['status'] ?? 'auto',
                        $event['event_date']
                    );
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($event['event_name']) ?></strong></td>
                        <td><?= htmlspecialchars($event['event_date']) ?></td>
                        <td><?= htmlspecialchars($event['version_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($event['track'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($event['racer'] ?? '—') ?></td>
                        <td><?= (int) $event['session_count'] ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                        <td class="actions">

    <!-- Edit (always visible) -->
    <a href="event_form.php?id=<?= $event['event_id'] ?>"
       class="btn btn--outline btn--sm">Edit</a>

    <!-- View Sessions (always visible) -->
    <a href="sessions.php?event_id=<?= $event['event_id'] ?>"
       class="btn btn--outline btn--sm">Sessions</a>

    <!-- End Event (live → completed) -->
    <?php if ($statusKey === 'live'): ?>
    <form method="POST" action="event_status.php">
        <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
        <input type="hidden" name="status"   value="completed">
        <button type="submit" class="btn btn--outline btn--sm">End Event</button>
    </form>

    <!-- Force Live (upcoming → live) -->
    <?php elseif ($statusKey === 'upcoming'): ?>
    <form method="POST" action="event_status.php">
        <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
        <input type="hidden" name="status"   value="live">
        <button type="submit" class="btn btn--outline btn--sm">Force Live</button>
    </form>
    <?php endif; ?>

    <!-- Cancel (active events only) -->
    <?php if ($statusKey !== 'canceled' && $statusKey !== 'completed'): ?>
    <form method="POST" action="event_status.php"
          onsubmit="return confirm('Cancel this event?')">
        <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
        <input type="hidden" name="status"   value="canceled">
        <button type="submit" class="btn btn--danger btn--sm">Cancel</button>
    </form>
    <?php else: ?>
        <button class="btn btn--danger btn--sm btn--disabled" disabled>Cancel</button>
    <?php endif; ?>

</td>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- Recent Laps -->
    <section class="card">
        <div class="card-header">
            <h2>Recent Laps</h2>
        </div>

        <?php if (empty($recentLaps)): ?>
            <p class="empty-state">No laps recorded yet.</p>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Racer</th>
                        <th>Track</th>
                        <th>Lap #</th>
                        <th>Lap Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentLaps as $lap): ?>
                    <tr>
                        <td><?= htmlspecialchars($lap['event_name']) ?></td>
                        <td><?= htmlspecialchars($lap['racer'])      ?></td>
                        <td><?= htmlspecialchars($lap['track'])      ?></td>
                        <td><?= (int) $lap['lap_number']             ?></td>
                        <td><strong><?= htmlspecialchars($lap['lap_time']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
