document.addEventListener('DOMContentLoaded', function () {
    const menuButton = document.querySelector('.menu-toggle');
    const navigation = document.querySelector('.main-navigation');

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

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && navigation.classList.contains('is-open')) {
            navigation.classList.remove('is-open');
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.focus();
        }
    });
});
