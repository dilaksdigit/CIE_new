<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx §7 (G7 Gate Definition)
// SOURCE: CIE_v2.3_Enforcement_Edition.pdf §7.1 (Readiness Scoring + Thresholds)
// SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 (BusinessRules seed keys)
// SOURCE: CLAUDE.md §6 Gate Table + §4 DECISION-001 (Channel scope: Shopify + GMC only)

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

    private const CHANNEL_MAP = [
        'shopify'     => 'own_website',
        'own_website' => 'own_website',
        'website'     => 'own_website',
        'gmc'         => 'google_sge',
        'google_sge'  => 'google_sge',
        'google'      => 'google_sge',
    ];

    public function validate(Sku $sku): GateResult|array
    {
        $tier = $sku->tier;

        if ($tier === TierType::HARVEST || $tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G7_EXPERT,
                passed: true,
                reason: "G7 suspended for {$tier->displayName()} tier.",
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
                    'error_code'   => 'CHANNEL_READINESS_BELOW_THRESHOLD',
                    'user_message' => "Target channel '{$rawChannel}' is not recognised. "
                        . 'Supported channels: Shopify (own_website), GMC (google_sge).',
                ]
            );
        }

        $skuCode = $sku->sku_code;

        $readinessRow = DB::table('channel_readiness')
            ->where('sku_id', $skuCode)
            ->where('channel', $channel)
            ->first();

        $score = $readinessRow ? (int) $readinessRow->score : 0;

        $failures = [];

        if ($tier === TierType::HERO) {
            $primaryThreshold = (int) BusinessRules::get('readiness.hero_primary_channel_min');
            if ($score < $primaryThreshold) {
                $failures[] = $this->buildFailure($score, $primaryThreshold, $channel);
            }

            $allThreshold = (int) BusinessRules::get('readiness.hero_all_channels_min');
            $otherRows = DB::table('channel_readiness')
                ->where('sku_id', $skuCode)
                ->whereIn('channel', self::ALLOWED_CHANNELS)
                ->where('channel', '!=', $channel)
                ->get();

            foreach ($otherRows as $row) {
                $rowScore = (int) $row->score;
                if ($rowScore < $allThreshold) {
                    $failures[] = $this->buildFailure($rowScore, $allThreshold, $row->channel);
                }
            }
        } elseif ($tier === TierType::SUPPORT) {
            $supportThreshold = (int) BusinessRules::get('readiness.support_primary_channel_min');
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
