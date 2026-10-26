document.addEventListener('DOMContentLoaded', function () {
    const menuButton = document.querySelector('.menu-toggle');
    const navigation = document.querySelector('.main-navigation');
    const profileButton = document.querySelector('.profile-button');
    const profileMenu = document.querySelector('.profile-menu');

    if (!menuButton || !navigation) {
        return;
    }

    menuButton.addEventListener('click', function () {
        const isOpen = navigation.classList.toggle('is-open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
    });

    navigation.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            navigation.classList.remove('is-open');
            menuButton.setAttribute('aria-expanded', 'false');
        }
    });

    if (profileButton && profileMenu) {
        profileButton.addEventListener('click', function () {
            const isOpen = profileMenu.classList.toggle('is-open');
            profileButton.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('click', function (event) {
            if (!profileMenu.contains(event.target)) {
                profileMenu.classList.remove('is-open');
                profileButton.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (profileMenu && profileMenu.classList.contains('is-open')) {
            profileMenu.classList.remove('is-open');
            profileButton.setAttribute('aria-expanded', 'false');
            profileButton.focus();
        } else if (navigation.classList.contains('is-open')) {
            navigation.classList.remove('is-open');
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.focus();
        }
    });
});
