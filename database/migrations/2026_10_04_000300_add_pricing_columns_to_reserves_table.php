<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reserves', function (Blueprint $table) {
            // total = subtotal - discount + service_fee. Guardados para registrar o valor cobrado na época.
            $table->decimal('subtotal', 10, 2)->default(0)->after('check_out');
            $table->decimal('discount', 10, 2)->default(0)->after('subtotal');
            $table->decimal('service_fee', 10, 2)->default(0)->after('discount');
            $table->foreignId('coupon_id')->nullable()->after('service_fee')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reserves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['subtotal', 'discount', 'service_fee']);
        });
    }
};
