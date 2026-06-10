<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$sessionId = (int) ($_POST['session_id'] ?? 0);
$eventId   = (int) ($_POST['event_id']   ?? 0);
$from      = trim($_POST['from']         ?? '');

if ($sessionId === 0) {
    header('Location: dashboard.php');
    exit();
}

$conn = getConnection();
$stmt = $conn->prepare('DELETE FROM sessions WHERE session_id = ?');
$stmt->bind_param('i', $sessionId);
$stmt->execute();
$stmt->close();
$conn->close();

setFlash('success', 'Session deleted.');

if ($from === 'sessions') {
    header('Location: sessions.php');
} elseif ($eventId > 0) {
    header('Location: event_detail.php?id=' . $eventId);
} else {
    header('Location: dashboard.php');
}
exit();
