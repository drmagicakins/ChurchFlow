<?php

namespace App\Domains\Subventions\Services;

use App\Models\Loan;
use App\Models\SubventionCalculation;
use App\Models\SubventionRule;
use App\Models\SubventionSubmission;
use Illuminate\Support\Collection;

/**
 * Generalizes the legacy calculate-subvention.php formula:
 *
 *   retention = general_calc + minister_calc
 *   others    = admin_calc + salary
 *   shortfall = others - retention
 *   if shortfall > 0: remittance = shortfall - loan_deduction
 *   else:             remittance = 0
 *
 * into: sum every rule tagged 'retention', sum every rule tagged
 * 'deduction', and the same shortfall/remittance shape — but the rules
 * themselves (which figures, what rate, fixed vs. percentage vs. capped)
 * come entirely from the church's configured SubventionRuleSet, not from
 * PHP conditionals. See RccgLegacyRuleSetSeeder for the legacy Group 1/2/3
 * rates expressed this way.
 *
 * Two corrections versus the legacy behavior, both explained inline below:
 *   1. remittance_amount is clamped at zero (legacy could go negative).
 *   2. loan_deduction_applied is clamped at the shortfall (legacy always
 *      subtracted the full monthly_deduction even when it exceeded the
 *      shortfall, which is what produced the negative result above).
 * Calling this twice for the same submission never mutates anything in
 * place — it always inserts a new SubventionCalculation row.
 */
class SubventionCalculationEngine
{
    public function calculate(SubventionSubmission $submission): SubventionCalculation
    {
        $rules = $submission->ruleSet->rules;
        $figures = $submission->figures ?? [];

        $breakdown = $rules->map(fn (SubventionRule $rule) => [
            'rule_id' => $rule->id,
            'name' => $rule->name,
            'classification' => $rule->classification,
            'amount' => $this->amountFor($rule, $figures),
        ]);

        $retentionTotal = $this->sumByClassification($breakdown, 'retention');
        $deductionTotal = $this->sumByClassification($breakdown, 'deduction');
        $shortfall = bcsub($deductionTotal, $retentionTotal, 2);

        $loan = Loan::query()
            ->where('organizational_unit_id', $submission->organizational_unit_id)
            ->where('status', 'active')
            ->first();

        $loanDeductionApplied = '0.00';
        $remittanceAmount = '0.00';

        if (bccomp($shortfall, '0', 2) > 0) {
            if ($loan) {
                // Corrected vs. legacy: never deduct more than the
                // shortfall itself, so remittance can never go negative.
                $loanDeductionApplied = bccomp($loan->monthly_deduction, $shortfall, 2) > 0
                    ? $shortfall
                    : (string) $loan->monthly_deduction;
            }

            $remittanceAmount = bcsub($shortfall, $loanDeductionApplied, 2);
        }

        return $submission->calculations()->create([
            'retention_total' => $retentionTotal,
            'deduction_total' => $deductionTotal,
            'shortfall' => $shortfall,
            'loan_id' => $loan?->id,
            'loan_deduction_applied' => $loanDeductionApplied,
            'remittance_amount' => $remittanceAmount,
            'breakdown' => $breakdown,
            'calculated_at' => now(),
        ]);
    }

    private function amountFor(SubventionRule $rule, array $figures): string
    {
        $base = (string) ($figures[$rule->base_field] ?? '0');

        return match ($rule->type) {
            'fixed' => (string) $rule->fixed_amount,
            'percentage' => bcdiv(bcmul($base, (string) $rule->rate, 4), '100', 2),
            'capped_percentage' => $this->capped($base, $rule),
            default => '0.00',
        };
    }

    private function capped(string $base, SubventionRule $rule): string
    {
        $computed = bcdiv(bcmul($base, (string) $rule->rate, 4), '100', 2);

        return $rule->cap_amount !== null && bccomp($computed, (string) $rule->cap_amount, 2) > 0
            ? (string) $rule->cap_amount
            : $computed;
    }

    private function sumByClassification(Collection $breakdown, string $classification): string
    {
        return $breakdown
            ->where('classification', $classification)
            ->reduce(fn (?string $carry, array $row) => bcadd($carry ?? '0', $row['amount'], 2), '0.00');
    }
}
