<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$conn      = getConnection();
$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$eventId   = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$isEdit    = $sessionId > 0;
$errors    = [];
$values    = ['participant_name' => '', 'car' => '', 'track' => '', 'best_lap_time' => ''];

// Make sure the event exists
$stmt = $conn->prepare('SELECT event_id, event_name FROM events WHERE event_id = ? LIMIT 1');
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    header('Location: dashboard.php');
    exit();
}

// Load existing session if editing
if ($isEdit) {
    $stmt = $conn->prepare('SELECT * FROM sessions WHERE session_id = ? AND event_id = ? LIMIT 1');
    $stmt->bind_param('ii', $sessionId, $eventId);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$session) {
        header('Location: event_detail.php?id=' . $eventId);
        exit();
    }

    $values = $session;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $values['participant_name'] = trim($_POST['participant_name'] ?? '');
    $values['car']              = trim($_POST['car']              ?? '');
    $values['track']            = trim($_POST['track']            ?? '');
    $values['best_lap_time']    = trim($_POST['best_lap_time']    ?? '');

    if ($values['participant_name'] == '') $errors[] = 'Participant name is required.';
    if ($values['best_lap_time']    == '') $errors[] = 'Lap time is required.';

    if (count($errors) == 0) {
        if ($isEdit) {
            $stmt = $conn->prepare(
                'UPDATE sessions SET participant_name=?, car=?, track=?, best_lap_time=? WHERE session_id=?'
            );
            $stmt->bind_param(
                'ssssi',
                $values['participant_name'],
                $values['car'],
                $values['track'],
                $values['best_lap_time'],
                $sessionId
            );
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO sessions (event_id, participant_name, car, track, best_lap_time) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'issss',
                $eventId,
                $values['participant_name'],
                $values['car'],
                $values['track'],
                $values['best_lap_time']
            );
        }

        $stmt->execute();
        $stmt->close();
        $conn->close();

        setFlash('success', $isEdit ? 'Session updated.' : 'Session added.');
        header('Location: event_detail.php?id=' . $eventId);
        exit();
    }
}

$conn->close();

$pageTitle = $isEdit ? 'Edit Session' : 'Add Session';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <a href="event_detail.php?id=<?php echo $eventId; ?>" class="back-link">← Back</a>
    <h2><?php echo $pageTitle; ?> — <?php echo htmlspecialchars($event['event_name']); ?></h2>
</div>

<?php if (count($errors) > 0) { ?>
    <div class="alert alert--error">
        <?php foreach ($errors as $err) { ?>
            <p><?php echo $err; ?></p>
        <?php } ?>
    </div>
<?php } ?>

<form method="POST" class="form">
    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">

    <div class="form-group">
        <label>Participant Name</label>
        <input type="text" name="participant_name"
               value="<?php echo htmlspecialchars($values['participant_name']); ?>" required>
    </div>
    <div class="form-group">
        <label>Car</label>
        <input type="text" name="car"
               value="<?php echo htmlspecialchars($values['car']); ?>">
    </div>
    <div class="form-group">
        <label>Track</label>
        <input type="text" name="track"
               value="<?php echo htmlspecialchars($values['track']); ?>">
    </div>
    <div class="form-group">
        <label>Best Lap Time</label>
        <input type="text" name="best_lap_time" placeholder="e.g. 1:23.456"
               value="<?php echo htmlspecialchars($values['best_lap_time']); ?>" required>
    </div>

    <button type="submit" class="btn btn--primary">
        <?php echo $isEdit ? 'Save Changes' : 'Add Session'; ?>
    </button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
