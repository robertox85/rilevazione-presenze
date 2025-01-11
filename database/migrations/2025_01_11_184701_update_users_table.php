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
        Schema::table('users', function (Blueprint $table) {
            $table->string('surname', 50)->nullable();
            $table->string('tax_code', 16)->unique()->nullable();
            $table->enum('contract_type', ['FULL_TIME', 'PART_TIME', 'EXTERNAL'])->nullable();
            $table->string('employee_id', 50)->unique()->nullable();

            $table->boolean('active')->default(true);

            // Basic privacy
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->boolean('geolocation_consent')->default(false);

            $table->softDeletes();

            DB::statement('ALTER TABLE users ADD CONSTRAINT chk_email CHECK (email LIKE "%@%.%")');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('surname');
            $table->dropColumn('tax_code');
            $table->dropColumn('contract_type');
            $table->dropColumn('employee_id');
            $table->dropColumn('active');
            $table->dropColumn('privacy_accepted_at');
            $table->dropColumn('geolocation_consent');
            $table->dropTimestamps();
            $table->dropSoftDeletes();
        });
    }
};
