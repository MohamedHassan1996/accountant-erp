<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->nullable()->after('extra_price');
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit_price']);
        });
    }
};
