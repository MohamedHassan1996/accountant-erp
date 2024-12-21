<?php

use App\Traits\CreatedUpdatedByMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('iva', 60)->nullable();
            $table->string('ragione_sociale')->nullable();
            $table->string('cf', 60)->nullable();
            $table->text('note')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('hours_per_month', 20)->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $this->CreatedUpdatedByRelationship($table);
            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');

    }
};
