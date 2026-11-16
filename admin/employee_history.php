<?php
require_once __DIR__ . '/includes/header.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$selectedDate = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

// User details
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('employee', 'supervisor')");
$userStmt->execute([$userId]);
$employee = $userStmt->fetch();

if (!$employee) {
    echo "
    <div class='container py-5'>
        <div class='alert alert-danger border-0 shadow-sm rounded-4 p-4 d-flex align-items-center gap-3'>
            <i class='bi bi-exclamation-octagon-fill fs-2'></i>
            <div>
                <h5 class='mb-1 fw-bold'>Staff Member Not Found</h5>
                <p class='mb-0 text-muted'>The requested user could not be located or has invalid permissions.</p>
            </div>
            <a href='tracking_list.php' class='btn btn-dark btn-sm ms-auto'>Back to Tracking</a>
        </div>
    </div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Location history for the selected date
$locStmt = $pdo->prepare("
    SELECT id, latitude, longitude, address, battery_level, speed, recorded_at 
    FROM locations 
    WHERE user_id = ? AND DATE(recorded_at) = ? 
    ORDER BY recorded_at DESC
");
$locStmt->execute([$userId, $selectedDate]);
$history = $locStmt->fetchAll();

$isSupervisor = $employee['role'] === 'supervisor';
?>

<!-- Leaflet CSS for Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    :root {
        --saas-border: #e2e8f0;
        --saas-card-bg: #ffffff;
        --saas-primary: #0284c7;
    }

    .saas-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1.15rem;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }

    /* Timeline Styling */
    .timeline-container {
        max-height: 580px;
        overflow-y: auto;
        padding: 0.75rem;
    }

    .timeline-card {
        position: relative;
        padding: 0.9rem 1rem 0.9rem 1.25rem;
        margin-bottom: 0.65rem;
        border-radius: 0.85rem;
        background: #f8fafc;
        border: 1px solid var(--saas-border);
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: block;
    }

    .timeline-card:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        transform: translateX(3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    }

    .timeline-card.active-point {
        background: #f0f9ff;
        border-color: #38bdf8;
        box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.25);
    }

    .timeline-indicator {
        position: absolute;
        left: 0.45rem;
        top: 1.15rem;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #94a3b8;
    }

    .timeline-card.active-point .timeline-indicator {
        background-color: #0284c7;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.2);
    }

    /* Battery Pill */
    .battery-pill {
        font-size: 0.72rem;
        padding: 2px 7px;
        border-radius: 9999px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    /* Map Viewport */
    #map {
        height: 580px;
        width: 100%;
        border-radius: 0 0 1.15rem 1.15rem;
        z-index: 1;
    }

    .badge-pill-role {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
    }

    .role-supervisor {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #dbeafe;
    }

    .role-employee {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }
</style>

<div class="container-fluid px-lg-4 py-3">

    <!-- Top Header & Profile Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($employee['name']) ?></h4>
                <span class="badge-pill-role <?= $isSupervisor ? 'role-supervisor' : 'role-employee' ?>">
                    <i class="bi <?= $isSupervisor ? 'bi-shield-check' : 'bi-person' ?>"></i>
                    <?= ucfirst($employee['role']) ?>
                </span>
            </div>
            <div class="text-muted small d-flex flex-wrap gap-3">
                <span><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($employee['phone']) ?></span>
                <span><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($employee['email']) ?></span>
                <span><i class="bi bi-geo-alt me-1"></i>Route Tracking Log</span>
            </div>
        </div>

        <div>
            <a href="tracking_list.php" class="btn btn-light border text-secondary rounded-3 px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Monitor</span>
            </a>
        </div>
    </div>

    <!-- Date Filter Card -->
    <div class="saas-card p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="user_id" value="<?= $userId ?>">
            
            <div class="col-auto">
                <span class="text-muted small fw-bold text-uppercase d-flex align-items-center gap-1">
                    <i class="bi bi-calendar3"></i> Date Filter:
                </span>
            </div>

            <div class="col-auto">
                <input type="date" name="date" class="form-control form-control-sm rounded-3 border-light-subtle" value="<?= htmlspecialchars($selectedDate) ?>">
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary rounded-3 px-3 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-filter"></i> Apply
                </button>
            </div>

            <div class="col-auto ms-auto">
                <a href="employee_history.php?user_id=<?= $userId ?>" class="btn btn-sm btn-light border rounded-3 px-3 text-secondary">
                    Show Today
                </a>
            </div>
        </form>
    </div>

    <!-- Two-Column Grid: Timeline & Map -->
    <div class="row g-3">
        
        <!-- Left: Movement Timeline List -->
        <div class="col-lg-5 col-12">
            <div class="saas-card h-100">
                <div class="p-3 border-bottom border-light-subtle bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold text-dark">Timeline Events</span>
                        <span class="badge bg-light text-secondary border rounded-pill ms-2"><?= count($history) ?></span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-3 py-1 px-2 small">
                        <?= date('d M, Y', strtotime($selectedDate)) ?>
                    </span>
                </div>

                <div class="timeline-container">
                    <?php if (empty($history)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-clock-history fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <div class="fw-semibold">No location pings recorded</div>
                            <small class="text-muted">No movement captured for <?= date('d M, Y', strtotime($selectedDate)) ?></small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($history as $index => $row): 
                            $battery = (int)($row['battery_level'] ?? 0);
                            $batteryClass = $battery > 50 ? 'bg-success-subtle text-success border-success-subtle' : ($battery > 20 ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle' : 'bg-danger-subtle text-danger border-danger-subtle');
                            $addressSafe = !empty($row['address']) ? $row['address'] : 'Coordinates: ' . $row['latitude'] . ', ' . $row['longitude'];
                        ?>
                            <div class="timeline-card <?= $index === 0 ? 'active-point' : '' ?>" 
                                 id="timeline-item-<?= $index ?>"
                                 onclick="focusLocation(<?= $row['latitude'] ?>, <?= $row['longitude'] ?>, '<?= date('h:i:s A', strtotime($row['recorded_at'])) ?>', '<?= htmlspecialchars(addslashes($addressSafe)) ?>', <?= $index ?>)">
                                <span class="timeline-indicator"></span>
                                
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="fw-bold text-dark small d-flex align-items-center gap-1.5">
                                        <i class="bi bi-stopwatch text-primary"></i>
                                        <?= date('h:i:s A', strtotime($row['recorded_at'])) ?>
                                    </div>
                                    <?php if (!empty($row['battery_level'])): ?>
                                        <span class="battery-pill border <?= $batteryClass ?>">
                                            <i class="bi bi-battery-charging"></i> <?= $battery ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <p class="text-secondary small mb-1" style="line-height: 1.4;">
                                    <?= htmlspecialchars($addressSafe) ?>
                                </p>

                                <div class="d-flex justify-content-between align-items-center pt-1 border-top border-light-subtle">
                                    <span class="text-muted" style="font-size: 0.725rem;">
                                        <i class="bi bi-cursor me-1"></i>Click to pin on map
                                    </span>
                                    <?php if (!empty($row['speed'])): ?>
                                        <span class="text-muted" style="font-size: 0.725rem;">
                                            <i class="bi bi-speedometer2 me-1"></i><?= round($row['speed'], 1) ?> km/h
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Map View -->
        <div class="col-lg-7 col-12">
            <div class="saas-card">
                <div class="p-3 border-bottom border-light-subtle bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-map text-primary"></i> Route Path & Map Canvas
                    </span>
                    <button class="btn btn-sm btn-light border text-secondary rounded-2 py-0.5 px-2" onclick="recenterMap()">
                        <i class="bi bi-arrows-fullscreen"></i> Fit Route
                    </button>
                </div>
                <div class="p-0">
                    <div id="map"></div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    const locations = <?= json_encode($history) ?>;
    let map, marker, polyline;

    let defaultLat = 23.8103;
    let defaultLng = 90.4125;

    if (locations.length > 0) {
        defaultLat = parseFloat(locations[0].latitude);
        defaultLng = parseFloat(locations[0].longitude);
    }

    // Initialize Map with clean styling
    map = L.map('map', {
        zoomControl: true
    }).setView([defaultLat, defaultLng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Draw route path
    if (locations.length > 0) {
        let latlngs = locations.map(l => [parseFloat(l.latitude), parseFloat(l.longitude)]);
        
        polyline = L.polyline(latlngs, {
            color: '#0284c7',
            weight: 4,
            opacity: 0.8,
            dashArray: '2, 6',
            lineJoin: 'round'
        }).addTo(map);

        // Initial marker at latest point
        const initialAddress = locations[0].address || (locations[0].latitude + ', ' + locations[0].longitude);
        marker = L.marker([defaultLat, defaultLng]).addTo(map)
            .bindPopup("<div style='font-family: inherit;'><b class='text-primary'>Latest Location</b><br>" + initialAddress + "</div>")
            .openPopup();

        map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
    }

    function recenterMap() {
        if (polyline && map) {
            map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
        }
    }

    function focusLocation(lat, lng, time, address, index) {
        if (!map) return;

        // Highlight active list item
        document.querySelectorAll('.timeline-card').forEach(el => el.classList.remove('active-point'));
        const activeCard = document.getElementById('timeline-item-' + index);
        if (activeCard) activeCard.classList.add('active-point');

        if (marker) {
            map.removeLayer(marker);
        }

        marker = L.marker([lat, lng]).addTo(map);
        marker.bindPopup("<div style='font-family: inherit;'><b class='text-primary'><i class='bi bi-clock me-1'></i>" + time + "</b><br>" + address + "</div>").openPopup();

        map.flyTo([lat, lng], 17, {
            animate: true,
            duration: 1.1
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>