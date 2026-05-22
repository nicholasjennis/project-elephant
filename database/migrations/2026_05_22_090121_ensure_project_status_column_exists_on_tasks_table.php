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
        if (! Schema::hasColumn('tasks', 'project_status')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->string('project_status')->nullable()->after('phase_task');
            });
        }

        if (Schema::hasColumn('tasks', 'status')) {
            DB::statement("
                UPDATE tasks
                SET project_status = CASE
                    WHEN status = 'done' THEN 'DONE'
                    WHEN status = 'in_progress' THEN 'in progress'
                    WHEN status = 'blocked' THEN 'Wait for FBs'
                    ELSE 'Upcoming'
                END
                WHERE project_status IS NULL OR project_status = ''
            ");
        }

        DB::statement("
            UPDATE tasks
            SET project_status = 'Upcoming'
            WHERE project_status IS NULL OR project_status = ''
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep this no-op to avoid accidentally dropping a required column
        // in environments where other migrations depend on it.
    }
};
