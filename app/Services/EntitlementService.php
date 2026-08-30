<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Task;
use App\Models\Renewal;
use App\Models\DocumentFile;
use App\Models\HouseholdMember;

/**
 * Central entitlement resolver (command.txt §18-§20).
 *
 * The subscription belongs to the HOUSEHOLD, not the user. Controllers ask
 * this service whether an action is allowed instead of holding pricing rules.
 */
class EntitlementService
{
    // Free tier (command.txt §19).
    public const FREE_TASKS = 10;
    public const FREE_RENEWALS = 3;
    public const FREE_DOCUMENT_BYTES = 100 * 1024 * 1024; // 100 MB
    public const FREE_MEMBERS = 3;

    public const DOCUMENTS_PLAN_BYTES = 5 * 1024 * 1024 * 1024; // 5 GB

    /**
     * Per-plan entitlement limits. null = unlimited.
     */
    private const PLAN_LIMITS = [
        'free' => [
            'tasks' => self::FREE_TASKS,
            'renewals' => self::FREE_RENEWALS,
            'documents_bytes' => self::FREE_DOCUMENT_BYTES,
        ],
        'tasks' => [
            'tasks' => null,
            'renewals' => self::FREE_RENEWALS,
            'documents_bytes' => self::FREE_DOCUMENT_BYTES,
        ],
        'renewals' => [
            'tasks' => self::FREE_TASKS,
            'renewals' => null,
            'documents_bytes' => self::FREE_DOCUMENT_BYTES,
        ],
        'essentials' => [
            'tasks' => null,
            'renewals' => null,
            'documents_bytes' => self::FREE_DOCUMENT_BYTES,
        ],
        'documents' => [
            'tasks' => self::FREE_TASKS,
            'renewals' => self::FREE_RENEWALS,
            'documents_bytes' => self::DOCUMENTS_PLAN_BYTES,
        ],
        'complete' => [
            'tasks' => null,
            'renewals' => null,
            'documents_bytes' => self::DOCUMENTS_PLAN_BYTES,
        ],
    ];

    /**
     * Resolve the effective plan code for a household.
     * Falls back to "free" when there is no active subscription.
     */
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
        return self::PLAN_LIMITS[$planCode] ?? self::PLAN_LIMITS['free'];
    }

    public function canCreateTask(Household $household): bool
    {
        $limit = $this->getLimits($this->getPlanCode($household))['tasks'];
        if ($limit === null) {
            return true;
        }
        $active = Task::where('household_id', $household->id)
            ->where('status', '!=', 'completed')
            ->count();
        return $active < $limit;
    }

    public function canCreateRenewal(Household $household): bool
    {
        $limit = $this->getLimits($this->getPlanCode($household))['renewals'];
        if ($limit === null) {
            return true;
        }
        $active = Renewal::where('household_id', $household->id)
            ->where('status', '!=', 'completed')
            ->count();
        return $active < $limit;
    }

    /**
     * Whether an additional upload of $additionalBytes is allowed.
     */
    public function canUploadDocument(Household $household, int $additionalBytes = 0): bool
    {
        $limit = $this->getLimits($this->getPlanCode($household))['documents_bytes'];
        if ($limit === null) {
            return true;
        }
        return ($this->getStorageUsed($household) + $additionalBytes) <= $limit;
    }

    /**
     * Whether another member can be added to the household.
     * Free tier is capped at FREE_MEMBERS; paid plans are unlimited.
     */
    public function canAddMember(Household $household): bool
    {
        if ($this->getPlanCode($household) === 'free') {
            $count = HouseholdMember::where('household_id', $household->id)
                ->where('status', 'active')
                ->count();
            return $count < self::FREE_MEMBERS;
        }
        return true;
    }

    public function getStorageUsed(Household $household): int
    {
        return (int) DocumentFile::whereHas('document', function ($q) use ($household) {
            $q->where('household_id', $household->id);
        })->sum('file_size');
    }

    public function getStorageRemaining(Household $household): int
    {
        $limit = $this->getLimits($this->getPlanCode($household))['documents_bytes'];
        if ($limit === null) {
            return PHP_INT_MAX;
        }
        return max(0, $limit - $this->getStorageUsed($household));
    }

    /**
     * Full entitlement summary for the frontend (settings / usage screen).
     */
    public function summary(Household $household): array
    {
        $planCode = $this->getPlanCode($household);
        $limits = $this->getLimits($planCode);
        $used = $this->getStorageUsed($household);

        return [
            'plan' => $planCode,
            'limits' => [
                'tasks' => $limits['tasks'],
                'renewals' => $limits['renewals'],
                'documents_bytes' => $limits['documents_bytes'],
            ],
            'usage' => [
                'tasks' => Task::where('household_id', $household->id)->where('status', '!=', 'completed')->count(),
                'renewals' => Renewal::where('household_id', $household->id)->where('status', '!=', 'completed')->count(),
                'documents_bytes' => $used,
            ],
            'storage_remaining' => $this->getStorageRemaining($household),
        ];
    }
}
