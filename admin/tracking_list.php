<?php
require_once __DIR__ . '/includes/header.php';

// Fetch users with their latest location
$stmt = $pdo->query("
    SELECT u.id, u.name, u.phone, u.role, u.status,
           l.latitude, l.longitude, l.recorded_at, l.address
    FROM users u
    LEFT JOIN locations l ON l.id = (
        SELECT id FROM locations WHERE user_id = u.id ORDER BY recorded_at DESC LIMIT 1
    )
    WHERE u.role IN ('employee', 'supervisor')
    ORDER BY u.name ASC
");
$employees = $stmt->fetchAll();

$totalStaff = count($employees);
$activeTracking = count(array_filter($employees, fn($e) => !empty($e['latitude'])));
$untracked = $totalStaff - $activeTracking;
?>

<style>
    :root {
        --saas-border: #e2e8f0;
        --saas-bg-muted: #f8fafc;
        --saas-text-dark: #0f172a;
        --saas-text-muted: #64748b;
    }

    /* Metric Stat Cards */
    .metric-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1rem;
        padding: 1.15rem 1.25rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.06);
    }
    .metric-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }

    /* Main Container */
    .saas-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1.15rem;
        overflow: hidden;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
    }

    /* Table Custom Styles */
    .table thead th {
        background-color: #f8fafc;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--saas-border);
        padding: 1rem 1.25rem;
        white-space: nowrap;
    }

    .table tbody td {
        padding: 1rem 1.25rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
        color: #334155;
    }

    .table tbody tr {
        transition: background-color 0.15s ease;
    }
    .table tbody tr:hover {
        background-color: #f8fafc;
    }

    /* Avatar */
    .avatar-sm {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    /* Badges */
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

    .ping-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
    }
    .ping-live {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #d1fae5;
    }
    .ping-live .ping-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
    }
    .ping-none {
        background: #f8fafc;
        color: #94a3b8;
        border: 1px solid #e2e8f0;
    }
    .ping-none .ping-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #cbd5e1;
    }

    /* Buttons */
    .btn-action {
        border-radius: 0.6rem;
        font-weight: 500;
        font-size: 0.825rem;
        padding: 0.45rem 0.85rem;
        transition: all 0.15s ease-in-out;
    }

    /* Mobile Responsive Card Layout */
    @media (max-width: 991.98px) {
        .desktop-table-view {
            display: none !important;
        }
        .mobile-card-view {
            display: grid !important;
            grid-template-columns: 1fr;
            gap: 1rem;
            padding: 1rem;
        }
    }

    @media (min-width: 992px) {
        .mobile-card-view {
            display: none !important;
        }
    }

    .tracking-card-mobile {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1rem;
        padding: 1.15rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
</style>

<div class="container-fluid px-lg-4 py-3">

    <!-- Header Section -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Location Monitoring</h4>
            <p class="text-muted small mb-0">Track real-time GPS locations and movement logs for staff members</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.location.reload();" class="btn btn-light border text-secondary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm">
                <i class="bi bi-arrow-clockwise"></i>
                <span>Refresh Live Pings</span>
            </button>
        </div>
    </div>

    <!-- Quick Stat Widgets -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Staff Tracked</div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalStaff ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-success-subtle text-success">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Active With Coordinates</div>
                    <div class="fs-4 fw-bold text-dark"><?= $activeTracking ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-secondary-subtle text-secondary">
                    <i class="bi bi-slash-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">No Signal / Untracked</div>
                    <div class="fs-4 fw-bold text-dark"><?= $untracked ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Container Card -->
    <div class="saas-card">
        
        <!-- Search & Filter Controls -->
        <div class="p-3 border-bottom border-light-subtle bg-white">
            <div class="row g-2 justify-content-between align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 0.65rem 0 0 0.65rem;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="liveSearchInput" class="form-control border-start-0" placeholder="Search staff by name, phone or address..." style="border-radius: 0 0.65rem 0.65rem 0;">
                    </div>
                </div>
                <div class="col-md-3 col-6 ms-auto">
                    <select id="roleFilterSelect" class="form-select rounded-3">
                        <option value="all">All Roles</option>
                        <option value="supervisor">Supervisors</option>
                        <option value="employee">Employees</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 1. Desktop Table View -->
        <div class="desktop-table-view table-responsive mb-0">
            <table class="table align-middle mb-0" id="trackingTable">
                <thead>
                    <tr>
                        <th class="ps-4">Staff Member</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Last Reported Address</th>
                        <th>Last Ping Time</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-geo-slash fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No employee records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): 
                            $isSupervisor = $emp['role'] === 'supervisor';
                            $avatarBg = $isSupervisor ? 'bg-primary-subtle text-primary' : 'bg-light text-dark border';
                            $initial = strtoupper(substr($emp['name'], 0, 1));
                            $hasLocation = !empty($emp['latitude']) && !empty($emp['longitude']);
                            $addressText = !empty($emp['address']) ? $emp['address'] : ($hasLocation ? $emp['latitude'] . ', ' . $emp['longitude'] : 'No signal reported yet');
                        ?>
                            <tr class="tracking-row" 
                                data-name="<?= strtolower(htmlspecialchars($emp['name'])) ?>" 
                                data-phone="<?= htmlspecialchars($emp['phone']) ?>" 
                                data-address="<?= strtolower(htmlspecialchars($addressText)) ?>"
                                data-role="<?= $emp['role'] ?>">
                                
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm <?= $avatarBg ?>">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($emp['name']) ?></div>
                                            <div class="text-muted small" style="font-size: 0.8rem;">ID: #<?= $emp['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark small"><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($emp['phone']) ?></span>
                                </td>
                                <td>
                                    <span class="badge-pill-role <?= $isSupervisor ? 'role-supervisor' : 'role-employee' ?>">
                                        <i class="bi <?= $isSupervisor ? 'bi-shield-check' : 'bi-person' ?>"></i>
                                        <?= ucfirst($emp['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-start gap-2" style="max-width: 320px;">
                                        <i class="bi bi-geo-alt <?= $hasLocation ? 'text-primary' : 'text-muted' ?> mt-1"></i>
                                        <div>
                                            <span class="<?= $hasLocation ? 'text-dark fw-medium' : 'text-muted' ?> small d-block">
                                                <?= htmlspecialchars($addressText) ?>
                                            </span>
                                            <?php if ($hasLocation): ?>
                                                <a href="https://www.google.com/maps?q=<?= $emp['latitude'] ?>,<?= $emp['longitude'] ?>" target="_blank" class="small text-decoration-none text-info d-inline-flex align-items-center gap-1 mt-1">
                                                    <span>Open in Maps</span> <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($emp['recorded_at'])): ?>
                                        <span class="ping-tag ping-live">
                                            <span class="ping-dot"></span>
                                            <?= date('h:i A, d M', strtotime($emp['recorded_at'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="ping-tag ping-none">
                                            <span class="ping-dot"></span>
                                            Offline
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="employee_history.php?user_id=<?= $emp['id'] ?>" class="btn btn-action btn-outline-primary d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-clock-history"></i>
                                        <span>View Route</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. Mobile Responsive Card View -->
        <div class="mobile-card-view">
            <?php if (empty($employees)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-geo-slash fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No employee records found.
                </div>
            <?php else: ?>
                <?php foreach ($employees as $emp): 
                    $isSupervisor = $emp['role'] === 'supervisor';
                    $initial = strtoupper(substr($emp['name'], 0, 1));
                    $hasLocation = !empty($emp['latitude']) && !empty($emp['longitude']);
                    $addressText = !empty($emp['address']) ? $emp['address'] : ($hasLocation ? $emp['latitude'] . ', ' . $emp['longitude'] : 'No signal reported yet');
                ?>
                    <div class="tracking-card-mobile tracking-card-item" 
                         data-name="<?= strtolower(htmlspecialchars($emp['name'])) ?>" 
                         data-phone="<?= htmlspecialchars($emp['phone']) ?>" 
                         data-address="<?= strtolower(htmlspecialchars($addressText)) ?>"
                         data-role="<?= $emp['role'] ?>">
                        
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm <?= $isSupervisor ? 'bg-primary-subtle text-primary' : 'bg-light text-dark border' ?>">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($emp['name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($emp['phone']) ?></small>
                                </div>
                            </div>
                            <span class="badge-pill-role <?= $isSupervisor ? 'role-supervisor' : 'role-employee' ?>">
                                <?= ucfirst($emp['role']) ?>
                            </span>
                        </div>

                        <!-- Location Box -->
                        <div class="p-2.5 rounded-3 bg-light mb-3 small">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-geo-alt <?= $hasLocation ? 'text-primary' : 'text-muted' ?> mt-0.5"></i>
                                <div>
                                    <div class="<?= $hasLocation ? 'text-dark fw-medium' : 'text-muted' ?>">
                                        <?= htmlspecialchars($addressText) ?>
                                    </div>
                                    <?php if ($hasLocation): ?>
                                        <a href="https://www.google.com/maps?q=<?= $emp['latitude'] ?>,<?= $emp['longitude'] ?>" target="_blank" class="small text-decoration-none text-info d-inline-flex align-items-center gap-1 mt-1">
                                            <span>Open in Google Maps</span> <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Info & Action -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top border-light-subtle">
                            <div>
                                <?php if (!empty($emp['recorded_at'])): ?>
                                    <span class="ping-tag ping-live">
                                        <span class="ping-dot"></span>
                                        <?= date('h:i A, d M', strtotime($emp['recorded_at'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="ping-tag ping-none">
                                        <span class="ping-dot"></span> Offline
                                    </span>
                                <?php endif; ?>
                            </div>

                            <a href="employee_history.php?user_id=<?= $emp['id'] ?>" class="btn btn-action btn-outline-primary d-inline-flex align-items-center gap-1">
                                <i class="bi bi-clock-history"></i>
                                <span>History</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    // Live Filter & Search Logic
    const searchInput = document.getElementById('liveSearchInput');
    const roleFilter = document.getElementById('roleFilterSelect');
    const tableRows = document.querySelectorAll('.tracking-row');
    const mobileCards = document.querySelectorAll('.tracking-card-item');

    function filterTrackingData() {
        const query = searchInput.value.toLowerCase().trim();
        const role = roleFilter.value;

        // Desktop Table Rows
        tableRows.forEach(row => {
            const name = row.getAttribute('data-name');
            const phone = row.getAttribute('data-phone');
            const address = row.getAttribute('data-address');
            const rowRole = row.getAttribute('data-role');

            const matchesSearch = name.includes(query) || phone.includes(query) || address.includes(query);
            const matchesRole = (role === 'all') || (rowRole === role);

            row.style.display = (matchesSearch && matchesRole) ? '' : 'none';
        });

        // Mobile Cards
        mobileCards.forEach(card => {
            const name = card.getAttribute('data-name');
            const phone = card.getAttribute('data-phone');
            const address = card.getAttribute('data-address');
            const cardRole = card.getAttribute('data-role');

            const matchesSearch = name.includes(query) || phone.includes(query) || address.includes(query);
            const matchesRole = (role === 'all') || (cardRole === role);

            card.style.display = (matchesSearch && matchesRole) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterTrackingData);
    roleFilter.addEventListener('change', filterTrackingData);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>