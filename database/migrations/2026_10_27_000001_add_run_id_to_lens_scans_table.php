<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lens_scans', function (Blueprint $table) {
            $table->uuid('run_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('lens_scans', function (Blueprint $table) {
            $table->dropUnique(['run_id']);
            $table->dropColumn('run_id');
        });
    }
};
