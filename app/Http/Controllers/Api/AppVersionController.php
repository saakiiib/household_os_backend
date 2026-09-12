<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'in:ios,android'],
            'version' => ['required', 'string', 'max:30'],
            'build' => ['nullable', 'integer', 'min:0'],
        ]);

        $platform = $validated['platform'];
        $currentVersion = trim($validated['version']);
        $currentBuild = (int) ($validated['build'] ?? 0);
        $policy = (array) config("app_update.{$platform}", []);

        $enabled = (bool) config('app_update.enabled', true);
        $latestVersion = trim((string) ($policy['latest_version'] ?? ''));
        $latestBuild = (int) ($policy['latest_build'] ?? 0);
        $minimumVersion = trim((string) ($policy['minimum_version'] ?? ''));
        $minimumBuild = (int) ($policy['minimum_build'] ?? 0);
        $storeUrl = trim((string) ($policy['store_url'] ?? ''));

        // Never trap users in an update screen if a valid store destination has
        // not been configured yet. This is especially important before the iOS
        // App Store listing ID exists.
        $storeReady = str_starts_with($storeUrl, 'https://');
        $policyReady = $enabled && $storeReady && $latestVersion !== '';

        $updateAvailable = false;
        $updateRequired = false;

        if ($policyReady) {
            $updateAvailable = $this->isOlder(
                $currentVersion,
                $currentBuild,
                $latestVersion,
                $latestBuild
            );

            if ($minimumVersion !== '') {
                $updateRequired = $this->isOlder(
                    $currentVersion,
                    $currentBuild,
                    $minimumVersion,
                    $minimumBuild
                );
            }
        }

        return response()->json([
            'update_available' => $updateAvailable,
            'update_required' => $updateRequired,
            'latest_version' => $latestVersion,
            'latest_build' => $latestBuild,
            'minimum_version' => $minimumVersion,
            'minimum_build' => $minimumBuild,
            'store_url' => $storeUrl,
            'message' => (string) ($policy['message'] ?? ''),
            'check_interval_hours' => max(1, (int) config('app_update.check_interval_hours', 12)),
            'remind_later_hours' => max(1, (int) config('app_update.remind_later_hours', 24)),
        ]);
    }

    private function isOlder(
        string $currentVersion,
        int $currentBuild,
        string $targetVersion,
        int $targetBuild
    ): bool {
        $versionComparison = version_compare($currentVersion, $targetVersion);

        if ($versionComparison < 0) {
            return true;
        }

        if ($versionComparison > 0) {
            return false;
        }

        return $currentBuild < $targetBuild;
    }
}
