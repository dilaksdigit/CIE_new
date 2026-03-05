<?php

namespace App\Enums;

/**
 * Canonical role names per CIE spec 3.1 / 3.2 Permission Matrix.
 * CONTENT_LEAD = Portfolio Holder (edit + approve + assign briefs + effort reports).
 */
enum RoleType: string
{
    case CONTENT_EDITOR    = 'content_editor';
    case PRODUCT_SPECIALIST= 'product_specialist';
    case SEO_GOVERNOR      = 'seo_governor';
    case CHANNEL_MANAGER   = 'channel_manager';
    case AI_OPS            = 'ai_ops';
    case CONTENT_LEAD      = 'content_lead';
    case FINANCE           = 'finance';
    case ADMIN             = 'admin';

    /** Human-readable description for UI (e.g. role selector). */
    public function description(): string
    {
        return match ($this) {
            self::ADMIN => 'Full access to all fields and actions. Modify 9-intent taxonomy. Manage users and roles. No restrictions — full system access.',
            self::CONTENT_EDITOR => 'Create and edit content fields; submit for review.',
            self::PRODUCT_SPECIALIST => 'Edit content and expert authority.',
            self::SEO_GOVERNOR => 'Assign clusters; modify cluster intent; approve cluster changes.',
            self::CHANNEL_MANAGER => 'Edit content; manage channel mappings.',
            self::AI_OPS => 'Run AI audits; manage golden queries.',
            self::CONTENT_LEAD => 'Portfolio Holder: publish, assign briefs, effort reports.',
            self::FINANCE => 'Trigger tier recalculation; ERP sync; golden queries.',
        };
    }

    public static function fromDatabase(string $name): ?self
    {
        return match (strtoupper($name)) {
            'CONTENT_EDITOR'    => self::CONTENT_EDITOR,
            'PRODUCT_SPECIALIST'=> self::PRODUCT_SPECIALIST,
            'SEO_GOVERNOR'      => self::SEO_GOVERNOR,
            'CHANNEL_MANAGER'   => self::CHANNEL_MANAGER,
            'AI_OPS'            => self::AI_OPS,
            'CONTENT_LEAD'      => self::CONTENT_LEAD,
            'PORTFOLIO_HOLDER'  => self::CONTENT_LEAD,
            'FINANCE'           => self::FINANCE,
            'ADMIN'             => self::ADMIN,
            default             => null,
        };
    }

}
