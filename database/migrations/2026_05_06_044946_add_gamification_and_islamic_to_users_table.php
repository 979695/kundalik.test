<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('xp')->default(0);
            $table->integer('level')->default(1);
            $table->integer('streak')->default(0);
            $table->integer('laziness_score')->default(0);
            $table->boolean('islamic_reminders')->default(true);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('notified_before')->default(false);
            $table->boolean('notified_at')->default(false);
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['xp', 'level', 'streak', 'laziness_score', 'islamic_reminders']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['notified_before', 'notified_at', 'completed_at']);
        });
    }
};
