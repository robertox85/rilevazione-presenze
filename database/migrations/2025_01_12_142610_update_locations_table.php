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
        Schema::table('locations', function (Blueprint $table) {
            // set country_code as nullable
            $table->char('country_code', 2)->nullable()->change();
            $table->char('timezone', 50)->nullable()->change();
            $table->time('working_start_time')->nullable()->change();
            $table->time('working_end_time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
