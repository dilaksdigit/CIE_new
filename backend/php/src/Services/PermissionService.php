<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * CIE v2.3.1 / 3.2 — Role-based permission matrix.
 * Content editors CANNOT override validation gate failures.
 *
 * ADMIN: Full access to all fields and actions; modify 9-intent taxonomy;
 * manage users and roles. No restrictions — full system access.
 * Other roles: Only ADMIN + FINANCE can trigger tier recalculation;
 * only SEO_GOVERNOR can modify cluster intent statements;
 * only ADMIN can modify the 9-intent taxonomy.
 */
class PermissionService
{
    private const ROLE_ADMIN = 'ADMIN';
    private const ROLE_SEO_GOVERNOR = 'SEO_GOVERNOR';
    private const ROLE_CONTENT_EDITOR = 'CONTENT_EDITOR';
    private const ROLE_CONTENT_LEAD = 'CONTENT_LEAD';
    private const ROLE_PRODUCT_SPECIALIST = 'PRODUCT_SPECIALIST';
    private const ROLE_CHANNEL_MANAGER = 'CHANNEL_MANAGER';
    private const ROLE_FINANCE = 'FINANCE';
    private const ROLE_AI_OPS = 'AI_OPS';

    /** Content fields (create/edit). Matrix: Editor, Prod Spec, Ch Mgr YES; PH/Finance/Admin NO for content. */
    private const CONTENT_FIELDS = [
        'title', 'short_description', 'long_description', 'ai_answer_block', 'ai_answer_block_chars',
        'meta_description', 'best_for', 'not_for', 'faq_data', 'primary_intent',
    ];

    /** Expert authority / safety certs. Product Specialist only. */
    private const EXPERT_AUTHORITY_FIELDS = ['expert_authority_name', 'expert_authority', 'compliance_notes'];

    /** Publish / submit for review. Matrix: Editor, SEO Gov, Ch Mgr, PH YES. */
    private const PUBLISH_FIELDS = ['validation_status'];

    /** Cluster assignment. SEO Governor only. */
    private const CLUSTER_FIELDS = ['primary_cluster_id'];

    /**
     * All assigned roles (matches RBACMiddleware multi-role behaviour).
     *
     * @return string[]
     */
    private function roles(?Authenticatable $user): array
    {
        if (!$user) {
            return [];
        }

        if (method_exists($user, 'relationLoaded') && method_exists($user, 'loadMissing')) {
            $user->loadMissing('roles');
            if ($user->roles && $user->roles->isNotEmpty()) {
                return $user->roles
                    ->pluck('name')
                    ->map(fn ($n) => strtoupper((string) $n))
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        $role = $user->role ?? null;
        if ($role) {
            return [strtoupper((string) $role->name)];
        }

        return [];
    }

    private function hasAnyRole(?Authenticatable $user, array $allowed): bool
    {
        $allowed = array_map('strtoupper', $allowed);
        return !empty(array_intersect($this->roles($user), $allowed));
    }

    private function hasRole(?Authenticatable $user, string $role): bool
    {
        return in_array(strtoupper($role), $this->roles($user), true);
    }

    /** Create/edit content fields. CONTENT_EDITOR, PRODUCT_SPECIALIST, CHANNEL_MANAGER. ADMIN has full access. */
    public function canEditContentFields(?Authenticatable $user): bool
    {
        if ($this->hasRole($user, self::ROLE_ADMIN)) {
            return true;
        }
        return $this->hasAnyRole($user, [
            self::ROLE_CONTENT_EDITOR,
            self::ROLE_PRODUCT_SPECIALIST,
            self::ROLE_CHANNEL_MANAGER,
        ]);
    }

    /** Edit expert authority only. PRODUCT_SPECIALIST only (and ADMIN). */
    public function canEditExpertAuthority(?Authenticatable $user): bool
    {
        if ($this->hasRole($user, self::ROLE_ADMIN)) {
            return true;
        }
        return $this->hasRole($user, self::ROLE_PRODUCT_SPECIALIST);
    }

    /** Assign/change cluster_id. SEO_GOVERNOR only (and ADMIN). */
    public function canAssignCluster(?Authenticatable $user): bool
    {
        return $this->hasRole($user, self::ROLE_ADMIN)
            || $this->hasRole($user, self::ROLE_SEO_GOVERNOR);
    }

    /** Modify 9-intent taxonomy. ADMIN only. */
    public function canModifyIntentTaxonomy(?Authenticatable $user): bool
    {
        return $this->hasRole($user, self::ROLE_ADMIN);
    }

    /** Modify cluster intent statements. SEO_GOVERNOR only (spec: only SEO_GOVERNOR). */
    public function canModifyClusterIntent(?Authenticatable $user): bool
    {
        return $this->hasRole($user, self::ROLE_SEO_GOVERNOR);
    }

    /** Publish SKU / submit for review. Editor, SEO Gov, Ch Mgr, CONTENT_LEAD (PH). */
    public function canPublishSku(?Authenticatable $user, $sku): bool
    {
        if (!$user || !$sku) {
            return false;
        }
        if ($sku->tier === \App\Enums\TierType::KILL) {
            return false;
        }
        if ($this->hasRole($user, self::ROLE_ADMIN)) {
            return true;
        }
        return $this->hasAnyRole($user, [
            self::ROLE_CONTENT_EDITOR,
            self::ROLE_SEO_GOVERNOR,
            self::ROLE_CHANNEL_MANAGER,
            self::ROLE_CONTENT_LEAD,
        ]);
    }

    /** CONTENT_LEAD may only set validation_status (publish), not edit content — unless they hold other governance roles. */
    public function canOnlyPublish(?Authenticatable $user): bool
    {
        if (!$this->hasRole($user, self::ROLE_CONTENT_LEAD)) {
            return false;
        }
        if ($this->hasRole($user, self::ROLE_ADMIN)) {
            return false;
        }
        return !$this->canEditContentFields($user)
            && !$this->canAssignCluster($user)
            && !$this->canModifyClusterIntent($user);
    }

    /** Run AI audit. AI_OPS, ADMIN. */
    public function canRunAIAudit(?Authenticatable $user): bool
    {
        return $this->hasAnyRole($user, [self::ROLE_AI_OPS, self::ROLE_ADMIN]);
    }

    /** Manage golden queries. Matrix: Editor, Ch Mgr, AI Ops, PH, Finance, Admin. */
    public function canManageGoldenQueries(?Authenticatable $user): bool
    {
        if ($this->hasRole($user, self::ROLE_ADMIN)) {
            return true;
        }
        return $this->hasAnyRole($user, [
            self::ROLE_CONTENT_EDITOR,
            self::ROLE_CHANNEL_MANAGER,
            self::ROLE_AI_OPS,
            self::ROLE_CONTENT_LEAD,
            self::ROLE_FINANCE,
        ]);
    }

    /** Trigger tier recalculation. ADMIN + FINANCE only. */
    public function canTriggerTierRecalculation(?Authenticatable $user): bool
    {
        return $this->hasAnyRole($user, [self::ROLE_ADMIN, self::ROLE_FINANCE]);
    }

    /** ERP sync. Finance, Admin. */
    public function canTriggerERPSync(?Authenticatable $user): bool
    {
        return $this->hasAnyRole($user, [self::ROLE_FINANCE, self::ROLE_ADMIN]);
    }

    /** Manage users/roles. ADMIN only. */
    public function canManageUsers(?Authenticatable $user): bool
    {
        return $this->hasRole($user, self::ROLE_ADMIN);
    }

    /** Content editors CANNOT override validation gate failures. */
    public function canOverrideGateFailures(?Authenticatable $user): bool
    {
        return false;
    }

    /** View readiness / channel mappings. CHANNEL_MANAGER + any authenticated. */
    public function canViewReadiness(?Authenticatable $user): bool
    {
        return $user && $this->roles($user) !== [];
    }

    public function canManageChannelMappings(?Authenticatable $user): bool
    {
        return $this->hasAnyRole($user, [self::ROLE_ADMIN, self::ROLE_CHANNEL_MANAGER]);
    }

    /**
     * Allowed SKU update fields for the current user. KILL tier is not checked here (controller must 403).
     *
     * @return string[]
     */
    public function allowedSkuUpdateFields(?Authenticatable $user): array
    {
        $fields = ['lock_version'];

        if ($this->hasRole($user, self::ROLE_ADMIN)) {
            return array_merge(
                self::CONTENT_FIELDS,
                self::EXPERT_AUTHORITY_FIELDS,
                self::PUBLISH_FIELDS,
                self::CLUSTER_FIELDS,
                $fields
            );
        }

        if ($this->canEditContentFields($user)) {
            $fields = array_merge($fields, self::CONTENT_FIELDS);
        }
        if ($this->canEditExpertAuthority($user)) {
            $fields = array_merge($fields, self::EXPERT_AUTHORITY_FIELDS);
        }
        if ($this->canPublishSku($user, (object)[])) {
            $fields = array_merge($fields, self::PUBLISH_FIELDS);
        }
        if ($this->canAssignCluster($user)) {
            $fields = array_merge($fields, self::CLUSTER_FIELDS);
        }

        if ($this->canOnlyPublish($user)) {
            return array_merge(self::PUBLISH_FIELDS, $fields);
        }

        return array_unique($fields);
    }
}
