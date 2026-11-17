// Admin Navigation & Profile Dropdown Controls
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('adminMenuToggle');
    const adminNav = document.getElementById('adminNav');
    const profileBtn = document.getElementById('adminProfileBtn');
    const profileMenu = document.getElementById('adminProfileMenu');

    function updateAdminMenuIcon(isOpen) {
        if (!menuToggle) return;
        menuToggle.classList.toggle('isOpen', isOpen);
        menuToggle.setAttribute('aria-expanded', String(isOpen));
        const icon = menuToggle.querySelector('i');
        if (icon) {
            if (isOpen) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-xmark', 'fa-times');
            } else {
                icon.classList.remove('fa-xmark', 'fa-times');
                icon.classList.add('fa-bars');
            }
        }
    }

    function closeAdminNav() {
        if (!adminNav || !adminNav.classList.contains('isOpen')) return;
        adminNav.classList.remove('isOpen');
        updateAdminMenuIcon(false);
    }

    function closeAdminProfile() {
        if (!profileMenu || !profileMenu.classList.contains('isOpen')) return;
        profileMenu.classList.remove('isOpen');
        if (profileBtn) profileBtn.setAttribute('aria-expanded', 'false');
    }

    // Toggle Mobile Navigation
    if (menuToggle && adminNav) {
        menuToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = adminNav.classList.toggle('isOpen');
            updateAdminMenuIcon(isOpen);
            closeAdminProfile();
        });

        // Close nav when clicking a link
        adminNav.addEventListener('click', function (e) {
            if (e.target.closest('a')) {
                closeAdminNav();
            }
        });
    }

    // Toggle Profile Dropdown
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = profileMenu.classList.toggle('isOpen');
            profileBtn.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) {
                closeAdminNav();
            }
        });
    }

    // Close menus when clicking outside
    document.addEventListener('click', function (e) {
        if (profileMenu && !profileMenu.contains(e.target)) {
            closeAdminProfile();
        }
        if (adminNav && menuToggle && !adminNav.contains(e.target) && !menuToggle.contains(e.target)) {
            closeAdminNav();
        }
    });

    // Escape key listener to close menus
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (profileMenu && profileMenu.classList.contains('isOpen')) {
                closeAdminProfile();
                if (profileBtn) {
                    profileBtn.setAttribute('aria-expanded', 'false');
                    profileBtn.focus();
                }
            }
            if (adminNav && adminNav.classList.contains('isOpen')) {
                closeAdminNav();
                if (menuToggle) menuToggle.focus();
            }
        }
    });
});
