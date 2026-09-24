<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merged_employment_periods', function (Blueprint $table) {
            $table->index(['project_id', 'emp_id', 'date_from']);
        });
    }

    public function down(): void
    {
        Schema::table('merged_employment_periods', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'emp_id', 'date_from']);
        });
    }
};
