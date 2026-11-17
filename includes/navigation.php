<?php
// Shared navigation links and helper functions for Fix & Exchange.

if (!function_exists('get_site_navigation_items')) {
    /**
     * Return list of primary navigation items for the campus platform.
     *
     * @return array<int, array<string, mixed>>
     */
    function get_site_navigation_items(): array
    {
        return [
            [
                'title' => 'Home',
                'url' => '/fix-and-exchange/',
                'icon' => 'fa-house',
                'auth_only' => false,
            ],
            [
                'title' => 'Dashboard',
                'url' => '/fix-and-exchange/src/account/home.php',
                'icon' => 'fa-gauge-high',
                'auth_only' => true,
            ],
            [
                'title' => 'Marketplace',
                'url' => '/fix-and-exchange/src/buyer-seller/marketplace.php',
                'icon' => 'fa-store',
                'auth_only' => false,
            ],
            [
                'title' => 'Repair Hub',
                'url' => '/fix-and-exchange/src/repair-requester/repair-hub.php',
                'icon' => 'fa-screwdriver-wrench',
                'auth_only' => false,
            ],
            [
                'title' => 'Sustainability',
                'url' => '/fix-and-exchange/src/sustainability/dashboard.php',
                'icon' => 'fa-leaf',
                'auth_only' => false,
            ],
            [
                'title' => 'About Us',
                'url' => '/fix-and-exchange/about-us.php',
                'icon' => 'fa-users',
                'auth_only' => false,
            ],
        ];
    }
}
