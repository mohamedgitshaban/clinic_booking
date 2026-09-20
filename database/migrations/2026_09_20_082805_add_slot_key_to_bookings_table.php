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
        Schema::table('bookings', function (Blueprint $table) {
            // Null for cancelled bookings so a cancelled slot can be rebooked;
            // otherwise "{doctor_id}|{date}|{start_time}", uniquely constrained
            // so the database itself rejects a concurrent double booking of the
            // exact same slot (see Booking::booted()).
            $table->string('slot_key')->nullable()->after('status');
            $table->unique('slot_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['slot_key']);
            $table->dropColumn('slot_key');
        });
    }
};
