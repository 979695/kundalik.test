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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('alarm_mode')->default(true)->after('islamic_reminders');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('telegram_message_id')->nullable()->after('last_notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('alarm_mode');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('telegram_message_id');
        });
    }
};
