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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('device_id')->constrained();
            $table->date('date');
            $table->time('check_in');
            $table->time('check_out')->nullable();
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('date');

        });

        DB::statement('ALTER TABLE attendances ADD CONSTRAINT chk_check_in_coords CHECK (
                (check_in_latitude IS NULL AND check_in_longitude IS NULL) OR
                (check_in_latitude BETWEEN -90 AND 90 AND check_in_longitude BETWEEN -180 AND 180)
            )');

        DB::statement('ALTER TABLE attendances ADD CONSTRAINT chk_check_out_coords CHECK (
                (check_out_latitude IS NULL AND check_out_longitude IS NULL) OR
                (check_out_latitude BETWEEN -90 AND 90 AND check_out_longitude BETWEEN -180 AND 180)
            )');

        DB::statement('ALTER TABLE attendances ADD CONSTRAINT chk_check_times CHECK (check_out IS NULL OR check_in < check_out)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
