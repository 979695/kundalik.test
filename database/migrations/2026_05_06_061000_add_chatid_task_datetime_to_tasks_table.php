<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->bigInteger('chat_id')->nullable()->after('user_id');
            $table->string('task')->nullable()->after('chat_id');
            $table->timestamp('datetime')->nullable()->after('task');
        });

        $tasks = DB::table('tasks')->get();
        foreach ($tasks as $task) {
            $chatId = DB::table('users')->where('id', $task->user_id)->value('chat_id');
            DB::table('tasks')->where('id', $task->id)->update([
                'chat_id' => $chatId,
                'task' => $task->title,
                'datetime' => $task->scheduled_at,
            ]);
        }
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['chat_id', 'task', 'datetime']);
        });
    }
};
