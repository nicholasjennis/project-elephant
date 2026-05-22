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
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('project_status')->nullable()->after('phase_task');
            $table->dropUnique('tasks_sku_title_unique');
            $table->dropColumn(['title', 'status', 'project_status_raw', 'designer_name_raw', 'gpm_name_raw']);
            $table->unique(['sku', 'phase_task']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropUnique('tasks_sku_phase_task_unique');
            $table->string('title')->after('sku');
            $table->string('status')->default('new')->index()->after('gpm_user_id');
            $table->string('project_status_raw')->nullable()->after('phase_task');
            $table->string('designer_name_raw')->nullable()->after('ready_to_check_week');
            $table->string('gpm_name_raw')->nullable()->after('designer_name_raw');
            $table->dropColumn('project_status');
            $table->unique(['sku', 'title']);
        });
    }
};
