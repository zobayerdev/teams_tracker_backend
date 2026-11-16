<?php
require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

// Handle Assign / Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id   = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
    $supervisor_id = !empty($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : null;

    if ($employee_id <= 0) {
        $error = 'Please select a valid employee.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET supervisor_id = ? WHERE id = ? AND role = 'employee'");
        if ($stmt->execute([$supervisor_id, $employee_id])) {
            $success = 'Supervisor assignment updated successfully.';
        } else {
            $error = 'Failed to update assignment. Please try again.';
        }
    }
}

// Fetch all supervisors
$supStmt = $pdo->query("SELECT id, name, phone FROM users WHERE role = 'supervisor' AND status = 'active' ORDER BY name ASC");
$supervisors = $supStmt->fetchAll();

// Fetch all employees with their assigned supervisor name
$empStmt = $pdo->query("
    SELECT 
        e.id, e.name, e.phone, e.email, e.status, e.supervisor_id,
        s.name AS supervisor_name, s.phone AS supervisor_phone
    FROM users e
    LEFT JOIN users s ON e.supervisor_id = s.id
    WHERE e.role = 'employee'
    ORDER BY s.name ASC, e.name ASC
");
$employees = $empStmt->fetchAll();

$totalEmployees = count($employees);
$assignedCount = count(array_filter($employees, fn($e) => !empty($e['supervisor_id'])));
$unassignedCount = $totalEmployees - $assignedCount;
?>

<style>
    :root {
        --saas-border: #e2e8f0;
        --saas-bg-muted: #f8fafc;
        --saas-text-dark: #0f172a;
        --saas-text-muted: #64748b;
        --saas-primary: #0284c7;
    }

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

    .saas-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1.15rem;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }

    .form-label {
        font-size: 0.825rem;
        font-weight: 600;
        letter-spacing: 0.025em;
        text-transform: uppercase;
        color: #475569;
        margin-bottom: 0.4rem;
    }

    .input-group-text {
        background-color: #f8fafc;
        border-color: #e2e8f0;
        color: #64748b;
        border-radius: 0.75rem 0 0 0.75rem;
    }

    .form-select, .form-control {
        border-color: #e2e8f0;
        border-radius: 0 0.75rem 0.75rem 0;
        padding: 0.65rem 0.9rem;
        font-size: 0.92rem;
        color: #1e293b;
    }

    .form-select:focus, .form-control:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
    }

    .btn-save-assign {
        background: #0f172a;
        color: #ffffff;
        border: none;
        font-weight: 600;
        border-radius: 0.75rem;
        padding: 0.7rem 1.4rem;
        transition: all 0.2s ease;
    }
    .btn-save-assign:hover {
        background: #1e293b;
        color: #ffffff;
        transform: translateY(-1px);
    }

    /* Table Styles */
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

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
    }
    .status-active {
        background: #ecfdf5;
        color: #059669;
    }
    .status-active .badge-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #10b981;
    }
    .status-inactive {
        background: #fef2f2;
        color: #dc2626;
    }
    .status-inactive .badge-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #ef4444;
    }

    .assign-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .assign-active {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #dbeafe;
    }
    .assign-none {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fef3c7;
    }

    /* Target Highlight Animation when Edit Clicked */
    .highlight-card {
        animation: pulseBorder 1.2s ease-in-out;
    }
    @keyframes pulseBorder {
        0%, 100% { box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); }
        50% { box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.35); }
    }

    /* Mobile Responsive Card Setup */
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

    .mapping-card-mobile {
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
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Supervisor & Staff Mapping</h4>
            <p class="text-muted small mb-0">Assign supervisors to manage and oversee field employees</p>
        </div>
        <div>
            <a href="employee_create.php" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm" style="font-weight: 500;">
                <i class="bi bi-person-plus-fill"></i>
                <span>Add Staff Member</span>
            </a>
        </div>
    </div>

    <!-- Alert Banners -->
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
    <?php endif; ?>

    <!-- Stat Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Employees</div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalEmployees ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-success-subtle text-success">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Assigned to Supervisor</div>
                    <div class="fs-4 fw-bold text-dark"><?= $assignedCount ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-person-exclamation"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Unassigned Staff</div>
                    <div class="fs-4 fw-bold text-dark"><?= $unassignedCount ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Grid: Assignment Form (Left) & Mapping List (Right) -->
    <div class="row g-4">
        
        <!-- Left: Quick Assignment Box -->
        <div class="col-lg-4 col-12">
            <div class="saas-card h-100" id="assignmentCard">
                <div class="p-3 border-bottom border-light-subtle bg-white d-flex align-items-center gap-2">
                    <div class="metric-icon-box bg-primary-subtle text-primary" style="width: 36px; height: 36px; font-size: 1.1rem; border-radius: 8px;">
                        <i class="bi bi-diagram-3-fill"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Assign Supervisor</h6>
                        <small class="text-muted">Link an employee to a lead</small>
                    </div>
                </div>

                <div class="p-4">
                    <form method="POST" id="assignForm">
                        
                        <!-- Select Employee -->
                        <div class="mb-3">
                            <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <select name="employee_id" id="employeeSelect" class="form-select" required>
                                    <option value="">-- Choose Employee --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" data-sup="<?= $emp['supervisor_id'] ?? '' ?>">
                                            <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['phone']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Select Supervisor -->
                        <div class="mb-4">
                            <label class="form-label">Assign Supervisor</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                                <select name="supervisor_id" id="supervisorSelect" class="form-select">
                                    <option value="">-- None (Unassign) --</option>
                                    <?php foreach ($supervisors as $sup): ?>
                                        <option value="<?= $sup['id'] ?>">
                                            <?= htmlspecialchars($sup['name']) ?> (<?= htmlspecialchars($sup['phone']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="text-muted d-block mt-1.5" style="font-size: 0.78rem;">
                                Select <strong>None</strong> to remove the supervisor link.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-save-assign w-100 d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-check2-circle"></i>
                            <span>Save Assignment</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Current Mapping List -->
        <div class="col-lg-8 col-12">
            <div class="saas-card">
                
                <!-- Search & Filter Bar -->
                <div class="p-3 border-bottom border-light-subtle bg-white">
                    <div class="row g-2 justify-content-between align-items-center">
                        <div class="col-md-6 col-12">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 0.65rem 0 0 0.65rem;">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" id="liveSearchInput" class="form-control border-start-0" placeholder="Search employee or supervisor..." style="border-radius: 0 0.65rem 0.65rem 0;">
                            </div>
                        </div>
                        <div class="col-md-4 col-12 ms-auto">
                            <select id="statusFilter" class="form-select rounded-3">
                                <option value="all">All Assignments</option>
                                <option value="assigned">Assigned Only</option>
                                <option value="unassigned">Unassigned Only</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table View -->
                <div class="desktop-table-view table-responsive mb-0">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Employee Details</th>
                                <th>Contact</th>
                                <th>Assigned Supervisor</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        No employee records found in system.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $row): 
                                    $isAssigned = !empty($row['supervisor_name']);
                                    $initial = strtoupper(substr($row['name'], 0, 1));
                                ?>
                                    <tr class="mapping-row" 
                                        data-name="<?= strtolower(htmlspecialchars($row['name'])) ?>"
                                        data-phone="<?= htmlspecialchars($row['phone']) ?>"
                                        data-supname="<?= strtolower(htmlspecialchars($row['supervisor_name'] ?? '')) ?>"
                                        data-assigned="<?= $isAssigned ? 'assigned' : 'unassigned' ?>">
                                        
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-sm bg-light text-dark border">
                                                    <?= $initial ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($row['name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($row['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark small"><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($row['phone']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($isAssigned): ?>
                                                <div class="assign-badge assign-active">
                                                    <i class="bi bi-shield-check"></i>
                                                    <div>
                                                        <div><?= htmlspecialchars($row['supervisor_name']) ?></div>
                                                        <small class="opacity-75" style="font-size: 0.72rem;"><?= htmlspecialchars($row['supervisor_phone']) ?></small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="assign-badge assign-none">
                                                    <i class="bi bi-dash-circle"></i> Unassigned
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge-status <?= $row['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                                <span class="badge-status-dot"></span>
                                                <?= ucfirst($row['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-2 px-3 py-1" onclick="setEditData(<?= $row['id'] ?>, '<?= $row['supervisor_id'] ?? '' ?>')">
                                                <i class="bi bi-pencil-square me-1"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Responsive Card View -->
                <div class="mobile-card-view">
                    <?php if (empty($employees)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No employee records found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($employees as $row): 
                            $isAssigned = !empty($row['supervisor_name']);
                            $initial = strtoupper(substr($row['name'], 0, 1));
                        ?>
                            <div class="mapping-card-mobile mapping-card-item"
                                 data-name="<?= strtolower(htmlspecialchars($row['name'])) ?>"
                                 data-phone="<?= htmlspecialchars($row['phone']) ?>"
                                 data-supname="<?= strtolower(htmlspecialchars($row['supervisor_name'] ?? '')) ?>"
                                 data-assigned="<?= $isAssigned ? 'assigned' : 'unassigned' ?>">
                                
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-sm bg-light text-dark border">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($row['name']) ?></h6>
                                            <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['phone']) ?></small>
                                        </div>
                                    </div>
                                    <span class="badge-status <?= $row['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                        <span class="badge-status-dot"></span>
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </div>

                                <div class="p-2.5 rounded-3 bg-light my-3 small">
                                    <span class="text-muted d-block mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Supervisor Assigned</span>
                                    <?php if ($isAssigned): ?>
                                        <div class="fw-semibold text-primary d-flex align-items-center gap-1.5">
                                            <i class="bi bi-shield-check"></i>
                                            <?= htmlspecialchars($row['supervisor_name']) ?> (<?= htmlspecialchars($row['supervisor_phone']) ?>)
                                        </div>
                                    <?php else: ?>
                                        <div class="text-warning-emphasis d-flex align-items-center gap-1.5 fw-medium">
                                            <i class="bi bi-dash-circle"></i> No supervisor assigned
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex justify-content-end pt-1 border-top border-light-subtle">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-2 px-3 py-1" onclick="setEditData(<?= $row['id'] ?>, '<?= $row['supervisor_id'] ?? '' ?>')">
                                        <i class="bi bi-pencil-square me-1"></i> Modify Assignment
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>

</div>

<script>
    function setEditData(empId, supId) {
        const empSelect = document.getElementById('employeeSelect');
        const supSelect = document.getElementById('supervisorSelect');
        const card = document.getElementById('assignmentCard');

        empSelect.value = empId;
        supSelect.value = supId ? supId : '';

        // Add soft highlight visual effect
        card.classList.remove('highlight-card');
        void card.offsetWidth; // Trigger reflow
        card.classList.add('highlight-card');

        // Smooth scroll to assignment form
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Auto-update supervisor dropdown if user picks an employee from the dropdown manually
    document.getElementById('employeeSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const currentSup = selectedOption.getAttribute('data-sup');
        document.getElementById('supervisorSelect').value = currentSup ? currentSup : '';
    });

    // Real-time Search and Filter Logic
    const searchInput = document.getElementById('liveSearchInput');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('.mapping-row');
    const mobileCards = document.querySelectorAll('.mapping-card-item');

    function filterData() {
        const query = searchInput.value.toLowerCase().trim();
        const filterVal = statusFilter.value;

        // Desktop Table Rows
        tableRows.forEach(row => {
            const name = row.getAttribute('data-name');
            const phone = row.getAttribute('data-phone');
            const supName = row.getAttribute('data-supname');
            const assigned = row.getAttribute('data-assigned');

            const matchesSearch = name.includes(query) || phone.includes(query) || supName.includes(query);
            const matchesFilter = (filterVal === 'all') || (assigned === filterVal);

            row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
        });

        // Mobile Cards
        mobileCards.forEach(card => {
            const name = card.getAttribute('data-name');
            const phone = card.getAttribute('data-phone');
            const supName = card.getAttribute('data-supname');
            const assigned = card.getAttribute('data-assigned');

            const matchesSearch = name.includes(query) || phone.includes(query) || supName.includes(query);
            const matchesFilter = (filterVal === 'all') || (assigned === filterVal);

            card.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterData);
    statusFilter.addEventListener('change', filterData);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>