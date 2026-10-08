<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // `amount` (pre-existing) becomes the subtotal; `tax_amount` is
            // added on top; `total` is a generated/accessor concept kept in
            // the model rather than a stored column, so nothing has to keep
            // two numbers in sync by hand. A negative `amount` here
            // represents a proration CREDIT (§13), not a charge — see
            // ProrationCalculator.
            $table->decimal('tax_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_amount'); // percentage, e.g. 7.50
        });

        // §28: SMS pricing (and other platform-operator settings) must be
        // configurable by a platform admin at runtime, not only via a
        // .env value that needs a deploy to change. This is the generic
        // key/value store PlatformSettingsService reads and writes; env
        // vars remain the fallback default for a setting that's never been
        // touched from the admin UI.
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['tax_amount', 'tax_rate']);
        });
    }
};
