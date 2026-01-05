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
    Schema::create('dominus_runs', function (Blueprint $table) {
        $table->id();
        $table->date('date');                 // fecha que se procesa
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finished_at')->nullable();
        $table->string('status', 20)->default('running'); // running|ok|failed
        $table->unsignedInteger('branches')->default(0);
        $table->unsignedInteger('inserted')->default(0);
        $table->unsignedInteger('updated')->default(0);
        $table->unsignedInteger('skipped')->default(0);
        $table->text('error')->nullable();
        $table->timestamps();

        $table->index(['date', 'status']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dominus_runs');
    }
};
