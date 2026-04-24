<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lens_scans', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('scan_mode', 20);
            $table->json('urls_scanned');
            $table->unsignedInteger('total_issues')->default(0);
            $table->unsignedInteger('level_a_count')->default(0);
            $table->unsignedInteger('level_aa_count')->default(0);
            $table->unsignedInteger('level_aaa_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lens_scans');
    }
};
