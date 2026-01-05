<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dominus_runs', function (Blueprint $table) {
            // si consultas por status/fecha/tiempo
            $table->index(['status', 'date'], 'idx_runs_status_date');
            $table->index(['started_at'], 'idx_runs_started_at');
            $table->index(['finished_at'], 'idx_runs_finished_at');
        });

        Schema::table('dominus_sales', function (Blueprint $table) {
            $table->index(['run_id'], 'idx_sales_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('dominus_runs', function (Blueprint $table) {
            $table->dropIndex('idx_runs_status_date');
            $table->dropIndex('idx_runs_started_at');
            $table->dropIndex('idx_runs_finished_at');
        });

        Schema::table('dominus_sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_run_id');
        });
    }
};
