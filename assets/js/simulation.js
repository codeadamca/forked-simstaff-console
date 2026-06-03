const LAP_DURATION_MS = 5000;

let running      = false;
let sessionStart = null;
let lapStart     = null;
let animFrame    = null;
let currentLap   = 0;
let lapTimes     = [];

const carDot         = document.getElementById('car-dot');
const timerDisplay   = document.getElementById('timerDisplay');
const lapDisplay     = document.getElementById('lapCounter');
const lapList        = document.getElementById('lapList');
const btnStart       = document.getElementById('startBtn');
const btnEnd         = document.getElementById('endBtn');
const btnCompleteLap = document.getElementById('btn-complete-lap');

function formatTime(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes      = Math.floor(totalSeconds / 60);
    const seconds      = totalSeconds % 60;
    return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
}

function getTrackWidth() {
    const track = document.getElementById('trackLine');
    return track ? track.offsetWidth : 500;
}

function renderLaps() {
    if (!lapList) return;
    lapList.innerHTML = '';

    const bestMs = Math.min(...lapTimes.map(l => l.lap_time_ms));

    lapTimes.forEach(lap => {
        const div = document.createElement('div');
        const best = lap.lap_time_ms === bestMs ? ' ⭐' : '';
        div.textContent = 'Lap ' + lap.lap_number + ':  ' + lap.lap_time + best;
        if (lap.lap_time_ms === bestMs) div.style.color = '#ffd700';
        lapList.appendChild(div);
    });
}

function recordLap() {
    if (!running) return;

    const now       = performance.now();
    const lapMs     = Math.round(now - lapStart);
    const formatted = formatTime(lapMs);

    currentLap++;
    lapTimes.push({
        lap_number:  currentLap,
        lap_time_ms: lapMs,
        lap_time:    formatted
    });

    lapStart = performance.now();
    lapDisplay.textContent = 'LAP ' + currentLap;
    renderLaps();

    if (carDot) carDot.style.left = '0px';
}

function animate(timestamp) {
    if (!running) return;

    const sessionElapsed = timestamp - sessionStart;
    timerDisplay.textContent = formatTime(sessionElapsed);

    const lapElapsed = performance.now() - lapStart;
    const progress   = Math.min(lapElapsed / LAP_DURATION_MS, 1);
    const trackWidth = getTrackWidth();

    if (carDot) carDot.style.left = (progress * trackWidth) + 'px';

    animFrame = requestAnimationFrame(animate);
}

btnStart.addEventListener('click', function () {
    if (running) return;

    running = true;
    currentLap = 0;
    lapTimes = [];
    sessionStart = performance.now();
    lapStart = sessionStart;

    lapDisplay.textContent = 'LAP 0';
    lapList.innerHTML = '';

    btnStart.disabled = true;
    btnCompleteLap.disabled = false;
    btnEnd.disabled = false;

    animFrame = requestAnimationFrame(animate);
});

btnCompleteLap.addEventListener('click', function () {
    if (!running) return;
    recordLap();
});

btnEnd.addEventListener('click', function () {
    if (!running && lapTimes.length === 0) {
        alert('Start a session first!');
        return;
    }

    running = false;
    cancelAnimationFrame(animFrame);

    btnStart.disabled       = false;
    btnCompleteLap.disabled = true;
    btnEnd.disabled         = true;

    if (lapTimes.length === 0) {
        alert('No laps recorded.');
        return;
    }

    const sessionId = new URLSearchParams(window.location.search).get('session_id');

    console.log('session_id from URL:', sessionId);
    console.log('laps to send:', lapTimes);

    fetch('../api/save_laps.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            session_id: sessionId,
            laps: lapTimes
        })
    })
    .then(res => res.json())
    .then(data => {
        console.log('Response:', data);
        if (data.success) {
            window.location.href = '../pages/results.php?session_id=' + sessionId;
        } else {
            alert('Error saving: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('Network error while saving laps.');
    });
});

