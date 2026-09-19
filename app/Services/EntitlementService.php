<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Renewal;
use App\Models\Setting;
use App\Models\Task;

/**
 * Central household entitlement resolver.
 *
 * Limits are controlled from Platform Settings. Defaults below are only the
 * safe launch fallbacks used when an admin setting has not yet been saved.
 * null means unlimited.
 */
class EntitlementService
{
    private const DEFAULTS = [
        'free' => ['tasks' => 5, 'renewals' => 3, 'documents' => 5, 'storage_mb' => 50, 'members' => 2, 'max_file_mb' => 10],
        'tasks' => ['tasks' => null, 'renewals' => 3, 'documents' => 5, 'storage_mb' => 50, 'members' => 6, 'max_file_mb' => 10],
        'renewals' => ['tasks' => 5, 'renewals' => null, 'documents' => 5, 'storage_mb' => 50, 'members' => 6, 'max_file_mb' => 10],
        'essentials' => ['tasks' => null, 'renewals' => null, 'documents' => null, 'storage_mb' => 250, 'members' => 6, 'max_file_mb' => 10],
        'documents' => ['tasks' => 5, 'renewals' => 3, 'documents' => null, 'storage_mb' => 500, 'members' => 6, 'max_file_mb' => 10],
        'complete' => ['tasks' => null, 'renewals' => null, 'documents' => null, 'storage_mb' => 500, 'members' => 6, 'max_file_mb' => 10],
    ];

    public function getPlanCode(Household $household): string
    {
        $subscription = $household->subscription;
        if ($subscription && $subscription->isActive()) {
            $plan = $subscription->plan;
            if ($plan && $plan->code) {
                return $plan->code;
            }
        }
        return 'free';
    }

    public function getLimits(string $planCode): array
    {
        $code = array_key_exists($planCode, self::DEFAULTS) ? $planCode : 'free';
        $defaults = self::DEFAULTS[$code];

        $tasks = $this->settingLimit("entitlement_{$code}_tasks", $defaults['tasks']);
        $renewals = $this->settingLimit("entitlement_{$code}_renewals", $defaults['renewals']);
        $documents = $this->settingLimit("entitlement_{$code}_documents", $defaults['documents']);
        $members = $this->settingLimit("entitlement_{$code}_members", $defaults['members']);
        $storageMb = $this->settingNumber("entitlement_{$code}_storage_mb", $defaults['storage_mb']);
        $maxFileMb = $this->settingNumber("entitlement_{$code}_max_file_mb", $defaults['max_file_mb']);

        return [
            'tasks' => $tasks,
            'renewals' => $renewals,
            'documents' => $documents,
            'members' => $members,
            'documents_bytes' => (int) round($storageMb * 1024 * 1024),
            'max_file_bytes' => (int) round($maxFileMb * 1024 * 1024),
            'storage_warning_percent' => (int) $this->settingNumber('entitlement_storage_warning_percent', 80),
            'storage_critical_percent' => (int) $this->settingNumber('entitlement_storage_critical_percent', 95),
        ];
    }

    private function settingLimit(string $key, ?int $default): ?int
    {
        $fallback = $default === null ? 'unlimited' : (string) $default;
        $value = strtolower(trim((string) Setting::get($key, $fallback)));
        if ($value === '' || $value === 'unlimited' || $value === 'null' || $value === '-1') {
            return null;
        }
        return max(0, (int) $value);
    }

    private function settingNumber(string $key, float $default): float
    {
        $value = Setting::get($key, (string) $default);
        return is_numeric($value) ? max(0, (float) $value) : $default;
    }

    public function canCreateTask(Household $household): bool
    {
        $limit = $this->getLimits($this->getPlanCode($household))['tasks'];
        return $limit === null || $this->activeTaskCount($household) < $limit;
    }

    public function canCreateRenewal(Household $household): bool
    {
        $limit = $this->getLimits($this->getPlanCode($household))['renewals'];
        return $limit === null || $this->activeRenewalCount($household) < $limit;
    }

    public function canCreateDocument(Household $household): bool
    {
        $limit = $this->getLimits($this->getPlanCode($household))['documents'];
        return $limit === null || $this->documentCount($household) < $limit;
    }

    public function canUploadDocument(Household $household, int $additionalBytes = 0): bool
    {
        $limit = $this->getLimits($this->getPlanCode($household))['documents_bytes'];
        return ($this->getStorageUsed($household) + $additionalBytes) <= $limit;
    }

    public function canAddMember(Household $household): bool
    {
        $limit = $this->getLimits($this->getPlanCode($household))['members'];
        if ($limit === null) return true;
        $count = HouseholdMember::where('household_id', $household->id)->where('status', 'active')->count();
        return $count < $limit;
    }

    public function activeTaskCount(Household $household): int
    {
        return Task::where('household_id', $household->id)->where('status', '!=', 'completed')->count();
    }

    public function activeRenewalCount(Household $household): int
    {
        return Renewal::where('household_id', $household->id)->where('status', '!=', 'completed')->count();
    }

    public function documentCount(Household $household): int
    {
        return Document::where('household_id', $household->id)->count();
    }

    public function getStorageUsed(Household $household): int
    {
        return (int) DocumentFile::whereHas('document', fn($q) => $q->where('household_id', $household->id))->sum('file_size');
    }

    public function getStorageRemaining(Household $household): int
    {
        $limit = $this->getLimits($this->getPlanCode($household))['documents_bytes'];
        return max(0, $limit - $this->getStorageUsed($household));
    }

    public function summary(Household $household): array
    {
        $planCode = $this->getPlanCode($household);
        $limits = $this->getLimits($planCode);
        $used = $this->getStorageUsed($household);
        $usagePercent = $limits['documents_bytes'] > 0 ? round(($used / $limits['documents_bytes']) * 100, 2) : 100;
        $status = $usagePercent >= 100 ? 'full' : ($usagePercent >= $limits['storage_critical_percent'] ? 'almost_full' : ($usagePercent >= $limits['storage_warning_percent'] ? 'getting_full' : 'normal'));

        return [
            'plan' => $planCode,
            'limits' => [
                'tasks' => $limits['tasks'],
                'renewals' => $limits['renewals'],
                'documents' => $limits['documents'],
                'members' => $limits['members'],
                'documents_bytes' => $limits['documents_bytes'],
                'max_file_size_bytes' => $limits['max_file_bytes'],
            ],
            'usage' => [
                'tasks' => $this->activeTaskCount($household),
                'renewals' => $this->activeRenewalCount($household),
                'documents' => $this->documentCount($household),
                'documents_bytes' => $used,
            ],
            'storage_remaining' => $this->getStorageRemaining($household),
            'usage_percent' => $usagePercent,
            'storage_status' => $status,
            'can_create_task' => $this->canCreateTask($household),
            'can_create_renewal' => $this->canCreateRenewal($household),
            'can_create_document' => $this->canCreateDocument($household),
            'can_add_member' => $this->canAddMember($household),
            'can_upload' => $this->canCreateDocument($household) && $used < $limits['documents_bytes'],
        ];
    }
}
