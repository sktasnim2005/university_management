// ============================================================
//  University Management System — script.js
//  FIX: openModal / closeModal now use 'active' class
//       to match all PHP modal implementations
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    // ── Active nav link highlight ─────────────────────────────
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-links li a').forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });

    // ── Auto-dismiss flash messages after 4s ─────────────────
    const flash = document.getElementById('flashMsg');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.5s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    }

    // ── Live table search (works on any page with #searchInput) ─
    const searchInput = document.getElementById('searchInput');
    const dataTable   = document.getElementById('dataTable');
    if (searchInput && dataTable) {
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            dataTable.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter)
                    ? ''
                    : 'none';
            });
        });
    }

    // ── Close modal when clicking outside of it ───────────────
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // ── Close modal on Escape key ─────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(overlay => {
                overlay.classList.remove('active');
            });
        }
    });

});

// ── Modal helpers ─────────────────────────────────────────────
// FIX: was using class 'open' — now uses 'active' to match PHP pages
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

// ── Confirm delete helper ─────────────────────────────────────
function confirmDelete(url, name = 'this record') {
    if (confirm(`Are you sure you want to delete "${name}"?\nThis action cannot be undone.`)) {
        window.location.href = url;
    }
}

// ── Set all attendance selects to same value ──────────────────
function setAll(val) {
    document.querySelectorAll('.att-select').forEach(s => s.value = val);
}