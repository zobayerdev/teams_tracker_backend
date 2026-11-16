</div> <!-- Closing container from header -->

<footer class="app-footer mt-auto py-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 text-muted small">
            
            <!-- Left: Copyright & Brand -->
            <div class="d-flex align-items-center gap-2">
                <span class="system-status-indicator" title="System Operational"></span>
                <span>&copy; <?= date('Y') ?> <strong>Location Tracker</strong>. All rights reserved.</span>
            </div>

            <!-- Center/Right: Quick Links & Meta -->
            <ul class="nav list-unstyled d-flex align-items-center gap-3 mb-0">
                <li class="nav-item">
                    <span class="badge bg-light text-secondary border fw-normal px-2 py-1">v1.2.0</span>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php" class="footer-link">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="tracking_list.php" class="footer-link">Live Map</a>
                </li>
                <li class="nav-item">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle back-to-top" id="backToTopBtn" title="Back to top">
                        <i class="bi bi-chevron-up"></i>
                    </button>
                </li>
            </ul>

        </div>
    </div>
</footer>

<style>
    /* Sticky Footer Setup */
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    .app-footer {
        background-color: #ffffff;
        border-top: 1px solid #e2e8f0;
        margin-top: auto;
    }

    .footer-link {
        color: #64748b;
        text-decoration: none;
        transition: color 0.15s ease-in-out;
    }

    .footer-link:hover {
        color: #0f172a;
    }

    /* Live Operational Pulse Indicator */
    .system-status-indicator {
        width: 8px;
        height: 8px;
        background-color: #10b981;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
    }

    /* Back To Top Mini Button */
    .back-to-top {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        border-color: #cbd5e1;
        color: #64748b;
    }

    .back-to-top:hover {
        background-color: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a;
    }
</style>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Smooth scroll to top
    const backToTopBtn = document.getElementById('backToTopBtn');
    if (backToTopBtn) {
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
</script>
</body>
</html>