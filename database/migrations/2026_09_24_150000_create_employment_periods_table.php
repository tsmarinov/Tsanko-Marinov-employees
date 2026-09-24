<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('emp_id');
            $table->unsignedInteger('project_id');
            $table->date('date_from');
            $table->date('date_to');
            $table->index(['emp_id', 'project_id', 'date_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_periods');
    }
};
