document.addEventListener('DOMContentLoaded', function () {
    const menuButton = document.querySelector('.menu-toggle');
    const navigation = document.querySelector('.main-navigation');
    const profileButton = document.querySelector('.profile-button');
    const profileMenu = document.querySelector('.profile-menu');

    function updateMenuToggleIcon(isOpen) {
        if (!menuButton) return;
        menuButton.classList.toggle('is-open', isOpen);
        menuButton.setAttribute('aria-expanded', String(isOpen));
        const icon = menuButton.querySelector('.menu-toggle-icon') || menuButton.querySelector('i');
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

    function closeNavigation() {
        if (!navigation || !navigation.classList.contains('is-open')) return;
        navigation.classList.remove('is-open');
        updateMenuToggleIcon(false);
    }

    function closeProfileMenu() {
        if (!profileMenu || !profileMenu.classList.contains('is-open')) return;
        profileMenu.classList.remove('is-open');
        if (profileButton) profileButton.setAttribute('aria-expanded', 'false');
    }

    if (menuButton && navigation) {
        menuButton.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = navigation.classList.toggle('is-open');
            updateMenuToggleIcon(isOpen);
            closeProfileMenu();
        });

        navigation.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                closeNavigation();
            }
        });
    }

    if (profileButton && profileMenu) {
        profileButton.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = profileMenu.classList.toggle('is-open');
            profileButton.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) {
                closeNavigation();
            }
        });

        document.addEventListener('click', function (event) {
            if (!profileMenu.contains(event.target)) {
                closeProfileMenu();
            }
            if (navigation && menuButton && !navigation.contains(event.target) && !menuButton.contains(event.target)) {
                closeNavigation();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (profileMenu && profileMenu.classList.contains('is-open')) {
            closeProfileMenu();
            if (profileButton) profileButton.focus();
        } else if (navigation && navigation.classList.contains('is-open')) {
            closeNavigation();
            if (menuButton) menuButton.focus();
        }
    });
});

// Basic buyer/seller form and photo controls.
document.addEventListener('DOMContentLoaded', function () {
    // Show the latest saved messages after a send or page refresh.
    const messages = document.querySelector('.bs-chat-messages');
    if (messages) {
        messages.scrollTop = messages.scrollHeight;
    }

    const photoInput = document.getElementById('photos');
    const previews = document.getElementById('bs-new-photos');
    if (photoInput && previews) {
        let previewUrls = [];
        photoInput.addEventListener('change', function () {
            previewUrls.forEach(function (url) { URL.revokeObjectURL(url); });
            previewUrls = [];
            previews.replaceChildren();
            Array.from(photoInput.files).forEach(function (file) {
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return;
                const picture = document.createElement('img');
                const url = URL.createObjectURL(file);
                previewUrls.push(url);
                picture.src = url;
                picture.alt = 'New photo preview';
                picture.className = 'bs-thumbnail';
                previews.appendChild(picture);
            });
        });
    }
    const price = document.querySelector('.bs-photo-form #price');
    if (price) {
        function updatePrice() {
            const type = document.querySelector('.bs-photo-form input[name="listing_type"]:checked');
            const sale = type && type.value === 'Sale';
            price.disabled = !sale;
            price.required = Boolean(sale);
        }
        document.querySelectorAll('.bs-photo-form input[name="listing_type"]').forEach(function (radio) {
            radio.addEventListener('change', updatePrice);
        });
        updatePrice();
    }
    document.querySelectorAll('[data-listing-photo]').forEach(function (button) {
        button.addEventListener('click', function () {
            const mainPhoto = document.querySelector('img.bs-main-photo');
            if (mainPhoto) mainPhoto.src = button.dataset.listingPhoto;
        });
    });
    document.querySelectorAll('.bs-page form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
});
