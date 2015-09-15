<?php
/**
 * Notifier Configuration.
 *
 * This file contains list of URL which need to be notified when event happens
 * on Orbit. In the future this setting should be on database.
 */
return [
    'user-agent' => 'Orbit API Notifier/1',

    // Notifiy when customer login to check their membership number
    'user-login' => [

        // Retailer/Mall ID (string)
        '2' => [
            'url' => 'http://127.0.0.1/orbit-mall-api/public/orbit-notify/v1/check-member',
            'auth_type' => '',
            'auth_user' => '',
            'auth_password' => '',
            'notify_order' => 0,

            // 1=enabled or 0=disabled
            'enabled' => 1,

            // How long job will be release back onto the queue in seconds
            'release_time' => 300,

            // How many times we need to try before we delete the job
            'max_try' => 5,

            // Should we send email to notify that the job is failed?
            'email_on_failed' => FALSE,

            // Email address to send on job failed
            'email_addr' => [
                'Admin' => 'backend@dominopos.com'
            ],

            // Only used if we handle the request by ourself
            'internal' => [
                // You can use string '*' instead of array to
                // allow all IPs
                'allowed_ips' => [
                    '127.0.0.1'
                ]
            ]
        ],

    ],

    // Notifiy when customer data has been updated
    'user-update' => [

        // Retailer/Mall ID (string)
        '2' => [
            'url' => 'http://127.0.0.1/orbit-mall-api/public/orbit-notify/v1/update-member',
            'auth_type' => '',
            'auth_user' => '',
            'auth_password' => '',
            'notify_order' => 0,

            // 1=enabled or 0=disabled
            'enabled' => 1,

            // How long job will be release back onto the queue in seconds
            'release_time' => 300,

            // How many times we need to try before we delete the job
            'max_try' => 5,

            // Should we send email to notify that the job is failed?
            'email_on_failed' => FALSE,

            // Email address to send on job failed
            'email_addr' => [
                'Admin' => 'backend@dominopos.com'
            ],

            // Only used if we handle the request by ourself
            'internal' => [
                // You can use string '*' instead of array to
                // allow all IPs
                'allowed_ips' => [
                    '127.0.0.1'
                ]
            ]
        ],

    ],

    // Notifiy when new receipt for lucky draw has been saved
    'lucky-draw-number' => [

        // Retailer/Mall ID (string)
        '2' => [
            'url' => 'http://127.0.0.1/orbit-mall-api/public/orbit-notify/v1/lucky-draw-number',
            'auth_type' => '',
            'auth_user' => '',
            'auth_password' => '',
            'notify_order' => 0,

            // 1=enabled or 0=disabled
            'enabled' => 1,

            // How long job will be release back onto the queue in seconds
            'release_time' => 300,

            // How many times we need to try before we delete the job
            'max_try' => 5,

            // Should we send email to notify that the job is failed?
            'email_on_failed' => FALSE,

            // Email address to send on job failed
            'email_addr' => [
                'Admin' => 'backend@dominopos.com'
            ],

            // Only used if we handle the request by ourself
            'internal' => [
                // You can use string '*' instead of array to
                // allow all IPs
                'allowed_ips' => [
                    '127.0.0.1'
                ]
            ]
        ],

    ],
];