<?php
// POST endpoint for Python to submit a lap time
// Payload: {event_id, participant_name, car, track, best_lap_time, api_key}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Basic API key check
if (($data['api_key'] ?? '') != 'changeme123') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$eventId = (int)($data['event_id']?? 0);
$participantName = trim($data['participant_name'] ?? '');
$car = trim($data['car']?? '');
$track = trim($data['track']  ?? '');
$bestLapTime = trim($data['best_lap_time'] ?? '');

if ($eventId == 0 || $participantName == '' || $bestLapTime == '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$conn = getConnection();
$stmt = $conn->prepare(
    'INSERT INTO sessions (event_id, participant_name, car, track, best_lap_time) VALUES (?, ?, ?, ?, ?)'
);
$stmt->bind_param('issss', $eventId, $participantName, $car, $track, $bestLapTime);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'session_id' => $newId]);
