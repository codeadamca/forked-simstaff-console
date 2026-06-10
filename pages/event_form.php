<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$conn    = getConnection();
$eventId = (int) ($_GET['id'] ?? 0);
$isEdit  = $eventId > 0;
$errors  = [];
$values  = [
    'event_name' => '',
    'event_date' => date('Y-m-d'),
    'location'   => '',
    'notes'      => '',
    'version_id' => 0,
    'car'        => '',
    'track'      => '',
    'racer'      => '',
    'status'     => 'auto',
];

// ── Load all game versions ─────────────────────────────────
$versions = $conn->query('SELECT id, name FROM game_versions ORDER BY name ASC')
                 ->fetch_all(MYSQLI_ASSOC);

// ── Load existing event if editing ────────────────────────
if ($isEdit) {
    $stmt = $conn->prepare('SELECT * FROM events WHERE event_id = ? LIMIT 1');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        $conn->close();
        header('Location: dashboard.php');
        exit();
    }

    $values = array_merge($values, $event);
}

// ── Pre-load cars/tracks/racers if version already known ──
$cars = $tracks = $racers = [];
$selectedVersion = (int) ($values['version_id'] ?? 0);

if ($selectedVersion > 0) {
    $stmt = $conn->prepare('SELECT name FROM game_cars WHERE version_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $selectedVersion);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT name FROM game_tracks WHERE version_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $selectedVersion);
    $stmt->execute();
    $tracks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT name FROM game_racers WHERE version_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $selectedVersion);
    $stmt->execute();
    $racers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['event_name'] = trim($_POST['event_name'] ?? '');
    $values['event_date'] = trim($_POST['event_date'] ?? '');
    $values['location']   = trim($_POST['location']   ?? '');
    $values['notes']      = trim($_POST['notes']      ?? '');
    $values['version_id'] = (int) ($_POST['version_id'] ?? 0);
    $values['car']        = trim($_POST['car']         ?? '');
    $values['track']      = trim($_POST['track']       ?? '');
    $values['racer']      = trim($_POST['racer']       ?? '');
    $values['status']     = trim($_POST['status']      ?? 'auto');

    if ($values['event_name'] === '') $errors[] = 'Event name is required.';
    if ($values['event_date'] === '') $errors[] = 'Date is required.';
    if ($values['version_id'] === 0)  $errors[] = 'Please select a game version.';
    if ($values['car']        === '') $errors[] = 'Please select a car.';
    if ($values['track']      === '') $errors[] = 'Please select a track.';
    if ($values['racer']      === '') $errors[] = 'Please select a racer.';

    $allowed = ['auto', 'live', 'canceled', 'completed'];
    if (!in_array($values['status'], $allowed)) $errors[] = 'Invalid status selected.';

    if (empty($errors)) {
        if ($isEdit) {
            $stmt = $conn->prepare('
                UPDATE events
                SET event_name = ?, event_date = ?, location = ?,
                    notes = ?, version_id = ?, car = ?, track = ?, racer = ?,
                    status = ?
                WHERE event_id = ?
            ');
            $stmt->bind_param(
                'ssssissssi',
                $values['event_name'],
                $values['event_date'],
                $values['location'],
                $values['notes'],
                $values['version_id'],
                $values['car'],
                $values['track'],
                $values['racer'],
                $values['status'],
                $eventId
            );
        } else {
            $stmt = $conn->prepare('
                INSERT INTO events (event_name, event_date, location, notes, version_id, car, track, racer, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param(
                'ssssissss',
                $values['event_name'],
                $values['event_date'],
                $values['location'],
                $values['notes'],
                $values['version_id'],
                $values['car'],
                $values['track'],
                $values['racer'],
                $values['status']
            );
        }

        $stmt->execute();
        $stmt->close();
        $conn->close();

        setFlash('success', $isEdit ? 'Event updated.' : 'Event created.');
        header('Location: dashboard.php');
        exit();
    }
}

$conn->close();

$pageTitle = $isEdit ? 'Edit Event' : 'New Event';
include __DIR__ . '/../includes/header.php';
?>

<div class="form-page-header">
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    <h2><?= $pageTitle ?></h2>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert--danger" style="max-width:720px;margin:0 auto 20px;">
        <?php foreach ($errors as $err): ?>
            <p><?= htmlspecialchars($err) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" class="form-card">

    <div class="form-row">
        <div class="form-group">
            <label for="event_name">Event Name <span class="required">*</span></label>
            <input type="text" id="event_name" name="event_name"
                   value="<?= htmlspecialchars($values['event_name']) ?>"
                   required autofocus placeholder="e.g. Monaco GP Night">
        </div>
        <div class="form-group">
            <label for="event_date">Date <span class="required">*</span></label>
            <input type="date" id="event_date" name="event_date"
                   value="<?= htmlspecialchars($values['event_date']) ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label for="location">Location</label>
        <input type="text" id="location" name="location"
               value="<?= htmlspecialchars($values['location']) ?>"
               placeholder="e.g. Montreal, QC">
    </div>

    <!-- Game Setup -->
    <div class="form-section">
        <div class="form-section__label">Game Setup</div>

        <div class="form-group">
            <label for="sel-version">Game Version <span class="required">*</span></label>
            <select id="sel-version" name="version_id" required>
                <option value="">— Select Version —</option>
                <?php foreach ($versions as $v): ?>
                    <option value="<?= $v['id'] ?>"
                            data-id="<?= $v['id'] ?>"
                        <?= (int)$values['version_id'] === $v['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="sel-track">Track <span class="required">*</span></label>
                <select id="sel-track" name="track" required
                        <?= $selectedVersion === 0 ? 'disabled' : '' ?>>
                    <option value="">— Select Version First —</option>
                    <?php foreach ($tracks as $t): ?>
                        <option value="<?= htmlspecialchars($t['name']) ?>"
                            <?= $values['track'] === $t['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="sel-car">Car <span class="required">*</span></label>
                <select id="sel-car" name="car" required
                        <?= $selectedVersion === 0 ? 'disabled' : '' ?>>
                    <option value="">— Select Version First —</option>
                    <?php foreach ($cars as $c): ?>
                        <option value="<?= htmlspecialchars($c['name']) ?>"
                            <?= $values['car'] === $c['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="sel-racer">Racer <span class="required">*</span></label>
            <select id="sel-racer" name="racer" required
                    <?= $selectedVersion === 0 ? 'disabled' : '' ?>>
                <option value="">— Select Version First —</option>
                <?php foreach ($racers as $r): ?>
                    <option value="<?= htmlspecialchars($r['name']) ?>"
                        <?= $values['racer'] === $r['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Status (edit only) -->
    <?php if ($isEdit): ?>
    <div class="form-section">
        <div class="form-section__label">Event Status</div>

        <div class="form-group">
            <label for="sel-status">Status <span class="required">*</span></label>
            <select id="sel-status" name="status" required>
                <option value="auto"      <?= $values['status'] === 'auto'      ? 'selected' : '' ?>>Upcoming</option>
                <option value="live"      <?= $values['status'] === 'live'      ? 'selected' : '' ?>>Live</option>
                <option value="completed" <?= $values['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="canceled"  <?= $values['status'] === 'canceled'  ? 'selected' : '' ?>>Canceled</option>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <!-- Notes -->
    <div class="form-group">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="3"
                  placeholder="Any extra details..."><?= htmlspecialchars($values['notes']) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary">
            <?= $isEdit ? 'Save Changes' : 'Create Event' ?>
        </button>
        <a href="dashboard.php" class="btn btn--outline">Cancel</a>
    </div>

</form>

<script>
    const APP_BASE = "<?= rtrim(str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__ . '/..'), '/') ?>";
</script>

<script>
(function () {
    const selVersion = document.getElementById('sel-version');
    const selTrack   = document.getElementById('sel-track');
    const selCar     = document.getElementById('sel-car');
    const selRacer   = document.getElementById('sel-racer');

    const savedTrack = <?= json_encode($values['track'] ?? '') ?>;
    const savedCar   = <?= json_encode($values['car']   ?? '') ?>;
    const savedRacer = <?= json_encode($values['racer'] ?? '') ?>;

    const API_URL = window.location.origin
                  + window.location.pathname.replace(/\/pages\/[^\/]+$/, '')
                  + '/api/get_options.php';

    function resetSelect(el, placeholder) {
        el.innerHTML = `<option value="">${placeholder}</option>`;
        el.disabled = true;
    }

    function populate(el, items, savedValue, emptyLabel) {
        el.innerHTML = `<option value="">${emptyLabel}</option>`;
        items.forEach(item => {
            const opt       = document.createElement('option');
            opt.value       = item.name;
            opt.textContent = item.name;
            if (item.name === savedValue) opt.selected = true;
            el.appendChild(opt);
        });
        el.disabled = items.length === 0;
    }

    async function loadOptions(versionId) {
        if (!versionId) return;

        resetSelect(selTrack, '— Loading... —');
        resetSelect(selCar,   '— Loading... —');
        resetSelect(selRacer, '— Loading... —');

        try {
            const [tracks, cars, racers] = await Promise.all([
                fetch(`${API_URL}?type=tracks&version_id=${versionId}`).then(r => r.json()),
                fetch(`${API_URL}?type=cars&version_id=${versionId}`).then(r => r.json()),
                fetch(`${API_URL}?type=racers&version_id=${versionId}`).then(r => r.json()),
            ]);

            populate(selTrack,  tracks,  savedTrack,  tracks.length  ? '— Select Track —'  : '— No tracks —');
            populate(selCar,    cars,    savedCar,    cars.length    ? '— Select Car —'    : '— No cars —');
            populate(selRacer,  racers,  savedRacer,  racers.length  ? '— Select Racer —'  : '— No racers —');
        } catch (e) {
            console.error('get_options failed:', e);
            resetSelect(selTrack, '— Error loading —');
            resetSelect(selCar, '— Error loading —');
            resetSelect(selRacer, '— Error loading —');
        }
    }

    selVersion.addEventListener('change', function () {
        if (this.value) {
            loadOptions(this.value);
        } else {
            resetSelect(selTrack,  '— Select Version First —');
            resetSelect(selCar,    '— Select Version First —');
            resetSelect(selRacer,  '— Select Version First —');
        }
    });

    if (selVersion.value) {
        loadOptions(selVersion.value);
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
