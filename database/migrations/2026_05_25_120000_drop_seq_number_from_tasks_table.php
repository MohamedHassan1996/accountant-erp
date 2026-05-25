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
        if (Schema::hasColumn('tasks', 'seq_number')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('seq_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('tasks', 'seq_number')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('seq_number')->nullable()->after('number');
            });
        }
    }
};
