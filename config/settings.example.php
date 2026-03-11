<?php
/**
 * Application settings.
 *
 * IMPORTANT: Copy this file to settings.php (which is gitignored), then
 * replace the placeholder values.  Ideally place settings.php OUTSIDE the
 * web root and update the require path in login.php accordingly.
 *
 * Generate a bcrypt hash for each password with:
 *   php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
 */

return [
    /*
     * Authorised portal users.
     * Format:  'username' => '<bcrypt-hashed password>'
     */
    'users' => [
        'admin' => '$2y$12$REPLACE_WITH_BCRYPT_HASH_OF_YOUR_PASSWORD',
    ],

    /*
     * (Optional) Restrict the IP addresses that may access this portal.
     * Leave as an empty array to allow all IPs.
     * Example: ['10.0.0.0/8', '192.168.1.100']
     */
    'allowed_ips' => [],
];
