<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('color', 20)->default('sage');
            $table->boolean('is_active')->default(true);
        });

        $now = now();
        $franId = DB::table('users')->insertGetId([
            'name' => 'Fran', 'color' => 'sage', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $carmenId = DB::table('users')->insertGetId([
            'name' => 'Carmen', 'color' => 'clay', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('description')->constrained()->restrictOnDelete();
        });

        DB::table('tasks')->where('assignee', 'Fran')->update(['user_id' => $franId]);
        DB::table('tasks')->where('assignee', 'Carmen')->update(['user_id' => $carmenId]);
        DB::table('tasks')->whereNull('user_id')->update(['user_id' => $franId]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->dropIndex(['assignee']);
            $table->dropColumn('assignee');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('assignee', 30)->nullable();
        });

        DB::table('tasks')
            ->join('users', 'tasks.user_id', '=', 'users.id')
            ->update(['tasks.assignee' => DB::raw('users.name')]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->index('assignee');
        });

        DB::table('users')->whereNull('email')->whereIn('name', ['Fran', 'Carmen'])->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['color', 'is_active']);
        });
    }
};
