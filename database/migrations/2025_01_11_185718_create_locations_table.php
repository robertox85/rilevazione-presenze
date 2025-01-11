<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('address', 255)->nullable();
            $table->char('country_code', 2);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('timezone', 50);
            $table->time('working_start_time');
            $table->time('working_end_time');
            $table->string('working_days', 20)->default('1,2,3,4,5');
            $table->boolean('exclude_holidays')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE locations ADD CONSTRAINT chk_time CHECK (working_start_time < working_end_time)');
        DB::statement('ALTER TABLE locations ADD CONSTRAINT chk_coordinates CHECK (
                (latitude IS NULL AND longitude IS NULL) OR
                (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
            )');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
