<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

/** Pure reconciliation rules, shared by purchase verification and refresh. */
final class GoogleSubscriptionSnapshot
{
    public static function timestamp(string $value, string $timezone): DateTimeImmutable
    {
        // Require an absolute provider timestamp; never interpret it as device time.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T.+(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new RuntimeException('Google returned an invalid timestamp.');
        }
        // PHP accepts microseconds; Google's RFC3339 output may use nanoseconds.
        $value = preg_replace('/(\.\d{6})\d+(?=Z|[+-])/', '$1', $value);
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezone));
    }

    public static function milliseconds(int $value, string $timezone): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('U.u', sprintf('%d.%06d', intdiv($value, 1000), ($value % 1000) * 1000));
        if ($date === false) {
            throw new RuntimeException('Google returned an invalid epoch timestamp.');
        }
        return $date->setTimezone(new DateTimeZone($timezone));
    }

    /**
     * Retain a future verified end only for an active/grace response for the
     * same order. New orders may legitimately have shorter replacement periods.
     * Terminal states always win; a local date must not defeat revocation.
     *
     * @return array{status: string, expires_at: DateTimeInterface}
     */
    public static function resolve(
        string $state,
        DateTimeInterface $incomingEnd,
        DateTimeInterface $now,
        ?DateTimeInterface $localEnd = null,
        ?string $localStatus = null,
        bool $sameOrder = false,
    ): array {
        $status = match ($state) {
            'SUBSCRIPTION_STATE_ACTIVE' => 'active',
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => 'grace_period',
            'SUBSCRIPTION_STATE_CANCELED' => 'cancelled',
            'SUBSCRIPTION_STATE_EXPIRED',
            'SUBSCRIPTION_STATE_ON_HOLD',
            'SUBSCRIPTION_STATE_PAUSED',
            'SUBSCRIPTION_STATE_PENDING_PURCHASE_CANCELED' => 'expired',
            default => throw new RuntimeException('Google subscription state is not ready for reconciliation.'),
        };

        if (in_array($status, ['active', 'grace_period'], true)) {
            if ($sameOrder && in_array($localStatus, ['active', 'grace_period'], true)
                && $localEnd !== null && $localEnd > $now && $incomingEnd < $localEnd) {
                return ['status' => $status, 'expires_at' => $localEnd];
            }
            if ($incomingEnd <= $now) {
                // Retry later; never manufacture a month/year of store entitlement.
                throw new RuntimeException('Google reports active access with an expired timestamp. Please retry verification.');
            }
        }
        return ['status' => $status, 'expires_at' => $incomingEnd];
    }
}
