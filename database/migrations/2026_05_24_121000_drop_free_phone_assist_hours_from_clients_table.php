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
        if (Schema::hasColumn('clients', 'free_phone_assist_hours')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('free_phone_assist_hours');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('clients', 'free_phone_assist_hours')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->decimal('free_phone_assist_hours', 8, 2)->nullable()->after('hours_per_month');
            });
        }
    }
};
