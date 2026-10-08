<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            // `amount` (pre-existing) is the GRAND TOTAL — what's actually
            // sent to the payment gateway and charged (§4/§42: the person
            // must see the real total before paying). `subtotal` and the
            // tax fields are recorded here, at checkout time, and later
            // copied verbatim into the Invoice — deliberately NOT
            // recalculated at activation time, so a platform tax-rate
            // change between "person pays" and "webhook arrives" can never
            // invoice a different total than what was actually charged.
            $table->decimal('subtotal', 10, 2)->default(0)->after('amount');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'tax_rate', 'tax_amount']);
        });
    }
};
