<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;

$pageTitle = 'Simulation';
include __DIR__ . '/../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/simulation.css">


<div class="sim-wrapper">

    <h1 class="sim-title">F1 LAP SIMULATOR</h1>
    <p class="sim-subtitle">Session #<?= $sessionId ?> &nbsp;|&nbsp; Event #<?= $eventId ?></p>

    <!-- Track -->
    <div class="track-line-wrapper">
        <span class="flag start">START</span>
        <span class="flag finish">FINISH</span>
        <div class="track-line" id="trackLine">
            <div class="finish-marker"></div>
            <div id="car-dot"></div>
        </div>
    </div>

    <!-- Timer -->
    <div class="timer-display" id="timerDisplay">00:00</div>
    <div class="lap-counter" id="lapCounter">LAP 0</div>

    <!-- Buttons -->
    <div class="sim-controls">
        <button class="btn-sim btn-start" id="startBtn">START</button>
        <button id="btn-complete-lap" disabled>COMPLETE LAP</button>
        <button class="btn-sim btn-end" id="endBtn" disabled>END SESSION</button>
    </div>

    <!-- Lap list -->
    <div class="lap-list">
        <h3>LAP TIMES</h3>
        <div id="lapList"></div>
    </div>

</div>

<script src="../assets/js/simulation.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>