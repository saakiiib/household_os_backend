<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppleNotificationLog extends Model
{
    protected $fillable = [
        'notification_uuid',
        'notification_type',
        'subtype',
        'environment',
        'original_transaction_id',
        'processed_at',
        'status',
        'payload',
    ];

    protected $casts = [
        'notification_uuid' => 'string',
        'status' => 'boolean',
        'processed_at' => 'datetime',
    ];

    /**
     * Record a notification idempotently. Returns false if the
     * notificationUUID was already processed (command.txt §17 / §51 Rule 5).
     */
    public static function recordOrIgnore(
        string $uuid,
        string $type,
        ?string $subtype,
        ?string $environment,
        ?string $originalTransactionId,
        bool $status,
        ?string $payload
    ): ?self {
        return self::updateOrCreate(
            ['notification_uuid' => $uuid],
            [
                'notification_type' => $type,
                'subtype' => $subtype,
                'environment' => $environment,
                'original_transaction_id' => $originalTransactionId,
                'processed_at' => now(),
                'status' => $status,
                'payload' => $payload,
            ]
        );
    }
}
