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

    private function role(?Authenticatable $user): ?string
    {
        if (!$user) return null;
        $role = $user->role ?? null;
        return $role ? strtoupper((string) $role->name) : null;
    }

    /** Create/edit content fields. CONTENT_EDITOR, PRODUCT_SPECIALIST, CHANNEL_MANAGER. ADMIN has full access. */
    public function canEditContentFields(?Authenticatable $user): bool
    {
        $r = $this->role($user);
        if ($r === self::ROLE_ADMIN) return true;
        return in_array($r, [self::ROLE_CONTENT_EDITOR, self::ROLE_PRODUCT_SPECIALIST, self::ROLE_CHANNEL_MANAGER], true);
    }

    /** Edit expert authority only. PRODUCT_SPECIALIST only (and ADMIN). */
    public function canEditExpertAuthority(?Authenticatable $user): bool
    {
        $r = $this->role($user);
        if ($r === self::ROLE_ADMIN) return true;
        return $r === self::ROLE_PRODUCT_SPECIALIST;
    }

    /** Assign/change cluster_id. SEO_GOVERNOR only (and ADMIN). */
    public function canAssignCluster(?Authenticatable $user): bool
    {
        $r = $this->role($user);
        return $r === self::ROLE_ADMIN || $r === self::ROLE_SEO_GOVERNOR;
    }

    /** Modify 9-intent taxonomy. ADMIN only. */
    public function canModifyIntentTaxonomy(?Authenticatable $user): bool
    {
        return $this->role($user) === self::ROLE_ADMIN;
    }

    /** Modify cluster intent statements. SEO_GOVERNOR only (spec: only SEO_GOVERNOR). */
    public function canModifyClusterIntent(?Authenticatable $user): bool
    {
        return $this->role($user) === self::ROLE_SEO_GOVERNOR;
    }

    /** Publish SKU / submit for review. Editor, SEO Gov, Ch Mgr, CONTENT_LEAD (PH). */
    public function canPublishSku(?Authenticatable $user, $sku): bool
    {
        if (!$user || !$sku) return false;
        if (($sku->tier ?? '') === 'KILL') return false;
        $r = $this->role($user);
        if ($r === self::ROLE_ADMIN) return true;
        return in_array($r, [
            self::ROLE_CONTENT_EDITOR, self::ROLE_SEO_GOVERNOR, self::ROLE_CHANNEL_MANAGER, self::ROLE_CONTENT_LEAD,
        ], true);
    }

    /** CONTENT_LEAD may only set validation_status (publish), not edit content. */
    public function canOnlyPublish(?Authenticatable $user): bool
    {
        return $this->role($user) === self::ROLE_CONTENT_LEAD;
    }

    /** Run AI audit. AI_OPS, ADMIN. */
    public function canRunAIAudit(?Authenticatable $user): bool
    {
        $r = $this->role($user);
        return in_array($r, [self::ROLE_AI_OPS, self::ROLE_ADMIN], true);
    }

    /** Manage golden queries. Matrix: Editor, Ch Mgr, AI Ops, PH, Finance, Admin. */
    public function canManageGoldenQueries(?Authenticatable $user): bool
    {
        $r = $this->role($user);
        if ($r === self::ROLE_ADMIN) return true;
        return in_array($r, [
            self::ROLE_CONTENT_EDITOR, self::ROLE_CHANNEL_MANAGER, self::ROLE_AI_OPS,
            self::ROLE_CONTENT_LEAD, self::ROLE_FINANCE,
        ], true);
    }

    /** Trigger tier recalculation. ADMIN + FINANCE only. */
    public function canTriggerTierRecalculation(?Authenticatable $user): bool
    {
        $r = $this->role($user);
        return $r === self::ROLE_ADMIN || $r === self::ROLE_FINANCE;
    }

    /** ERP sync. Finance, Admin. */
    public function canTriggerERPSync(?Authenticatable $user): bool
    {
        $r = $this->role($user);
        return in_array($r, [self::ROLE_FINANCE, self::ROLE_ADMIN], true);
    }

    /** Manage users/roles. ADMIN only. */
    public function canManageUsers(?Authenticatable $user): bool
    {
        return $this->role($user) === self::ROLE_ADMIN;
    }

    /** Content editors CANNOT override validation gate failures. */
    public function canOverrideGateFailures(?Authenticatable $user): bool
    {
        return false;
    }

    /** View readiness / channel mappings. CHANNEL_MANAGER + any authenticated. */
    public function canViewReadiness(?Authenticatable $user): bool
    {
        return $user && $this->role($user) !== null;
    }

    public function canManageChannelMappings(?Authenticatable $user): bool
    {
        $r = $this->role($user);
        return $r === self::ROLE_ADMIN || $r === self::ROLE_CHANNEL_MANAGER;
    }

    /**
     * Allowed SKU update fields for the current user. KILL tier is not checked here (controller must 403).
     *
     * @return string[]
     */
    public function allowedSkuUpdateFields(?Authenticatable $user): array
    {
        $r = $this->role($user);
        $fields = ['lock_version'];

        if ($r === self::ROLE_ADMIN) {
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
