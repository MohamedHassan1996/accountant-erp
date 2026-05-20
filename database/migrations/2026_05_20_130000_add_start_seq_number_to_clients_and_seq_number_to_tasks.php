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
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('start_seq_number')->nullable()->after('limit_decreto');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('seq_number')->nullable()->after('number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('seq_number');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('start_seq_number');
        });
    }
};
