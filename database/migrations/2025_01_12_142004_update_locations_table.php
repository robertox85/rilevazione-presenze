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
            $table->string('street', 100)->after('address')->nullable();
            $table->string('number', 10)->after('street')->nullable();
            $table->string('city', 100)->after('number')->nullable();
            $table->string('state', 100)->after('city')->nullable();
            $table->string('postal_code', 10)->after('state')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('street');
            $table->dropColumn('number');
            $table->dropColumn('city');
            $table->dropColumn('state');
            $table->dropColumn('postal_code');
        });
    }
};
