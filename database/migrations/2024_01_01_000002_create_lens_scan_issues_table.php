<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lens_scan_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('lens_scans')->cascadeOnDelete();
            $table->string('rule_id', 255);
            $table->string('impact', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('help_url', 2048)->nullable();
            $table->text('html_snippet')->nullable();
            $table->string('selector', 500)->nullable();
            $table->json('tags')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('file_name', 500)->nullable();
            $table->unsignedInteger('line_number')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lens_scan_issues');
    }
};
