<?php

/*
 * App-level override for the kreait/laravel-firebase package config.
 *
 * We only override the FCM HTTP client timeout. Everything else (credentials,
 * cache store, logging, etc.) is merged in from the package's default config,
 * which still reads its values from the environment.
 *
 * Why this matters: NotificationService sends FCM synchronously inside the
 * per-minute scheduler commands. Without a timeout, an unresponsive Firebase
 * endpoint can hang the process and keep the command's overlap lock held, which
 * makes the next scheduled run skip — i.e. notifications intermittently not
 * arriving. A short timeout makes a slow call fail fast (and get logged) so the
 * lock is released and the scheduler keeps working.
 */
return [
    'projects' => [
        'app' => [
            'http_client_options' => [
                // Max seconds before an FCM request is considered timed out.
                'timeout' => 10,
            ],
        ],
    ],
];
