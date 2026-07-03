<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lens_scans', function (Blueprint $table) {
            $table->string('wcag_version', 3)->default('2.0')->after('scan_mode');
        });
    }

    public function down(): void
    {
        Schema::table('lens_scans', function (Blueprint $table) {
            $table->dropColumn('wcag_version');
        });
    }
};
