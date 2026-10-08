<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 — a plan may have no listed price.
 *
 * `monthly_price` was NOT NULL, which encoded an assumption that every plan
 * has a sticker price. Enterprise does not: it is quoted individually, so its
 * price is genuinely unknown at seed time. Storing 0.00 to satisfy the
 * constraint would have been much worse than nullable — 0 is a real number
 * that reads as "free", and it would flow into plan comparisons, proration
 * and the pricing page as a free tier.
 *
 * Null now means "quoted individually", and every consumer treats it that
 * way: Plan::displayPrice() renders "Custom", Plan::isSelfServe() returns
 * false so it gets a "Talk to us" CTA instead of a checkout button, and
 * PlanCatalog keeps it out of the purchasable list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('monthly_price', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('monthly_price', 10, 2)->nullable(false)->change();
        });
    }
};
