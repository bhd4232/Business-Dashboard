<?php

namespace App\Services\ImageGeneration;

use App\Models\Company;
use App\Models\GeneratedImage;
use App\Models\User;
use App\Services\AuditLogService;

/**
 * 11_AI_TOOL_MENU_IMAGE_GENERATION_PLAN.md §11 Phase 5 — the Image
 * Generation governance layer, stored at
 * `companies.settings->ai_tools->image_governance`:
 *
 *   {
 *     "monthly_caps":  { "sales_staff": 50, "manager": 0, ... },  // 0 = unlimited
 *     "review_roles":  ["sales_staff"]                            // roles whose
 *                                                                 // output needs
 *                                                                 // sign-off
 *   }
 *
 * All mechanism, no policy: every cap defaults to 0 (unlimited) and the
 * review-roles list defaults to empty, so the feature is inert until a super
 * admin sets real numbers on the Image Governance page — the same
 * "owner plugs in the values" contract as every external-credential setting.
 *
 * This is the only place that touches that JSON shape (mirrors
 * ImageProviderSettingsService / PromptGuideRepository for their own keys).
 */
class ImageGovernanceService
{
    public const SETTINGS_PATH = 'ai_tools.image_governance';

    /**
     * Roles a cap / review rule can target. `super_admin` is deliberately
     * omitted — it always bypasses both.
     *
     * @return array<string, string>
     */
    public static function governableRoles(): array
    {
        return collect(User::ROLES)
            ->reject(fn (string $label, string $key): bool => $key === 'super_admin')
            ->all();
    }

    /**
     * @return array{monthly_caps: array<string, int>, review_roles: array<int, string>}
     */
    public function all(Company $company): array
    {
        $stored = (array) data_get($company->settings, self::SETTINGS_PATH, []);
        $roles = array_keys(self::governableRoles());

        $caps = [];
        foreach ($roles as $role) {
            $caps[$role] = max(0, (int) data_get($stored, "monthly_caps.{$role}", 0));
        }

        $reviewRoles = collect((array) ($stored['review_roles'] ?? []))
            ->filter(fn ($role): bool => in_array($role, $roles, true))
            ->values()
            ->all();

        return ['monthly_caps' => $caps, 'review_roles' => $reviewRoles];
    }

    /**
     * @param  array{monthly_caps?: array<string, mixed>, review_roles?: array<int, string>}  $data
     */
    public function save(Company $company, array $data): void
    {
        $roles = array_keys(self::governableRoles());

        $caps = [];
        foreach ($roles as $role) {
            $value = max(0, (int) data_get($data, "monthly_caps.{$role}", 0));
            if ($value > 0) {
                $caps[$role] = $value;
            }
        }

        $reviewRoles = collect((array) ($data['review_roles'] ?? []))
            ->filter(fn ($role): bool => in_array($role, $roles, true))
            ->unique()
            ->values()
            ->all();

        $settings = (array) $company->settings;

        if ($caps === [] && $reviewRoles === []) {
            data_forget($settings, self::SETTINGS_PATH);
        } else {
            data_set($settings, self::SETTINGS_PATH, [
                'monthly_caps' => $caps,
                'review_roles' => $reviewRoles,
            ]);
        }

        $company->forceFill(['settings' => $settings])->save();
    }

    /** Monthly image cap for a role. 0 means unlimited. Super admin is always unlimited. */
    public function monthlyCapForRole(Company $company, ?string $role): int
    {
        if ($role === null || $role === 'super_admin') {
            return 0;
        }

        return (int) ($this->all($company)['monthly_caps'][$role] ?? 0);
    }

    public function roleRequiresReview(Company $company, ?string $role): bool
    {
        if ($role === null || $role === 'super_admin') {
            return false;
        }

        return in_array($role, $this->all($company)['review_roles'], true);
    }

    /**
     * Images (summed variation counts) a user has generated this calendar
     * month — failed generations do not count against the cap.
     */
    public function imagesUsedThisMonth(Company $company, User $user): int
    {
        return (int) GeneratedImage::query()
            ->where('user_id', $user->getKey())
            ->where('tool', GeneratedImage::TOOL_IMAGE_GENERATION)
            ->where('status', '!=', GeneratedImage::STATUS_FAILED)
            ->thisMonth()
            ->sum('variations_requested');
    }

    /** Images the user may still generate this month, or null when unlimited. */
    public function remainingThisMonth(Company $company, User $user): ?int
    {
        $cap = $this->monthlyCapForRole($company, $user->effectiveRole());

        if ($cap === 0) {
            return null;
        }

        return max(0, $cap - $this->imagesUsedThisMonth($company, $user));
    }

    public function wouldExceedCap(Company $company, User $user, int $requested): bool
    {
        $remaining = $this->remainingThisMonth($company, $user);

        return $remaining !== null && $requested > $remaining;
    }

    /**
     * A reviewer clears a pending generation for use, or blocks it. No-op on a
     * generation that was never pending. Writes an audit-trail entry either
     * way.
     */
    public function review(GeneratedImage $image, User $reviewer, bool $approved, ?string $note = null): void
    {
        if ($image->review_status !== GeneratedImage::REVIEW_PENDING) {
            return;
        }

        $note = trim((string) $note) ?: null;

        $image->forceFill([
            'review_status' => $approved ? GeneratedImage::REVIEW_APPROVED : GeneratedImage::REVIEW_REJECTED,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        app(AuditLogService::class)->record(
            $approved ? 'ai_image.review_approved' : 'ai_image.review_rejected',
            $image,
            null,
            array_filter([
                'reviewer' => $reviewer->name,
                'note' => $note,
            ]),
        );
    }

    /**
     * This-month usage totals for the governance dashboard.
     *
     * @return array{
     *     images: int, cost: float,
     *     by_user: array<int, array{name: string, images: int, cost: float}>,
     *     by_provider: array<int, array{label: string, images: int, cost: float}>
     * }
     */
    public function usageSummary(Company $company): array
    {
        $rows = GeneratedImage::query()
            ->where('tool', GeneratedImage::TOOL_IMAGE_GENERATION)
            ->where('status', '!=', GeneratedImage::STATUS_FAILED)
            ->thisMonth()
            ->with('user:id,name')
            ->get(['id', 'user_id', 'provider_label', 'api_format', 'variations_requested', 'estimated_cost']);

        $byUser = $rows
            ->groupBy('user_id')
            ->map(fn ($group) => [
                'name' => $group->first()->user?->name ?? 'Unknown',
                'images' => (int) $group->sum('variations_requested'),
                'cost' => round((float) $group->sum('estimated_cost'), 2),
            ])
            ->sortByDesc('cost')
            ->values()
            ->all();

        $byProvider = $rows
            ->groupBy(fn (GeneratedImage $row): string => $row->provider_label ?: ($row->api_format ?: 'Unknown'))
            ->map(fn ($group, string $label) => [
                'label' => $label,
                'images' => (int) $group->sum('variations_requested'),
                'cost' => round((float) $group->sum('estimated_cost'), 2),
            ])
            ->sortByDesc('cost')
            ->values()
            ->all();

        return [
            'images' => (int) $rows->sum('variations_requested'),
            'cost' => round((float) $rows->sum('estimated_cost'), 2),
            'by_user' => $byUser,
            'by_provider' => $byProvider,
        ];
    }
}
