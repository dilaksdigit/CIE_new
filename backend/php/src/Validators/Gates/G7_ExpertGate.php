<?php
// SOURCE: CIE_Master_Developer_Build_Spec Section 5 (Business Rules Config Layer); CIE_v231_Developer_Build_Pack G7 spec; CLAUDE.md — zero hard-coded thresholds

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;
use Illuminate\Support\Facades\DB;

class G7_ExpertGate implements GateInterface
{
    private const ALLOWED_CHANNELS = ['google_sge', 'own_website'];

    private const CHANNEL_TO_STORED = [
        'own_website' => 'shopify',
        'shopify'    => 'shopify',
        'google_sge' => 'gmc',
        'gmc'        => 'gmc',
        'google'     => 'gmc',
    ];

    private const CHANNEL_MAP = [
        'shopify'     => 'own_website',
        'own_website' => 'own_website',
        'website'     => 'own_website',
        'gmc'         => 'google_sge',
        'google_sge'  => 'google_sge',
        'google'      => 'google_sge',
    ];

    /** Threshold from business_rules (GATE-09) — keys channel.shopify_readiness_threshold, channel.gmc_readiness_threshold. */
    private static function thresholdForChannel(string $storedChannel): int
    {
        $key = 'channel.' . $storedChannel . '_readiness_threshold';
        $v = BusinessRules::get($key);
        return $v !== null && $v !== '' ? (int) $v : 85;
    }

    public function validate(Sku $sku): GateResult|array
    {
        $tier = $sku->tier;

        if ($tier === TierType::HARVEST || $tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G7_EXPERT,
                passed: true,
                reason: "Readiness check is not required for {$tier->displayName()} products.",
                blocking: false,
                metadata: ['status' => 'suspended']
            );
        }

        $rawChannel = strtolower(trim((string) ($sku->target_channel ?? '')));
        $channel = self::CHANNEL_MAP[$rawChannel] ?? null;

        if ($channel === null || !in_array($channel, self::ALLOWED_CHANNELS, true)) {
            return new GateResult(
                gate: GateType::G7_EXPERT,
                passed: false,
                reason: "Unknown or unsupported target channel: '{$rawChannel}'.",
                blocking: true,
                metadata: [
                    'user_message' => "Target channel '{$rawChannel}' is not recognised. Supported channels: Shopify (own_website), GMC (google_sge).",
                ]
            );
        }

        $skuCode = $sku->sku_code;
        $storedChannel = self::CHANNEL_TO_STORED[$channel] ?? $channel;

        $readinessRow = DB::table('channel_readiness')
            ->where('sku_id', $skuCode)
            ->where('channel', $storedChannel)
            ->first();

        $score = $readinessRow ? (int) $readinessRow->score : 0;

        $failures = [];

        if ($tier === TierType::HERO) {
            $primaryThreshold = self::thresholdForChannel($storedChannel);
            if ($score < $primaryThreshold) {
                $failures[] = $this->buildFailure($score, $primaryThreshold, $channel);
            }

            $otherRows = DB::table('channel_readiness')
                ->where('sku_id', $skuCode)
                ->whereIn('channel', array_values(self::CHANNEL_TO_STORED))
                ->where('channel', '!=', $storedChannel)
                ->get();

            foreach ($otherRows as $row) {
                $rowScore = (int) $row->score;
                $otherThreshold = self::thresholdForChannel($row->channel);
                if ($rowScore < $otherThreshold) {
                    $failures[] = $this->buildFailure($rowScore, $otherThreshold, $row->channel);
                }
            }
        } elseif ($tier === TierType::SUPPORT) {
            $supportThreshold = self::thresholdForChannel($storedChannel);
            if ($score < $supportThreshold) {
                $failures[] = $this->buildFailure($score, $supportThreshold, $channel);
            }
        }

        if (!empty($failures)) {
            return $failures;
        }

        return new GateResult(
            gate: GateType::G7_EXPERT,
            passed: true,
            reason: "Channel readiness score meets threshold for {$channel}.",
            blocking: false
        );
    }

    private function buildFailure(int $score, int $threshold, string $channel): GateResult
    {
        return new GateResult(
            gate: GateType::G7_EXPERT,
            passed: false,
            reason: "Readiness score {$score} is below required threshold {$threshold} for channel {$channel}.",
            blocking: true,
            metadata: [
                'error_code'   => 'CHANNEL_READINESS_BELOW_THRESHOLD',
                'detail'       => "Readiness score {$score} is below required threshold {$threshold} for channel {$channel}.",
                'user_message' => "This SKU's readiness score for {$channel} is {$score}/100. "
                    . "It must reach {$threshold} before publish. "
                    . 'Complete the missing fields shown in the readiness panel.',
            ]
        );
    }
}
