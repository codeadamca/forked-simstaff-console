<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$id     = (int) ($_POST['event_id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($id && in_array($status, ['auto', 'live', 'canceled', 'completed'])) {
    $conn = getConnection();
    $stmt = $conn->prepare('UPDATE events SET status = ? WHERE event_id = ?');
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    setFlash('success', 'Event status updated.');
}

header('Location: ../pages/dashboard.php');
exit;
