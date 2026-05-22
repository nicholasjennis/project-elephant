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
            $table->string('theme')->nullable()->after('description');
            $table->string('import_year')->nullable()->after('theme');
            $table->string('batch')->nullable()->after('import_year');
            $table->string('artwork_type')->nullable()->after('batch');
            $table->string('phase_task')->nullable()->after('artwork_type');
            $table->string('project_status_raw')->nullable()->after('phase_task');
            $table->string('quantity')->nullable()->after('project_status_raw');
            $table->string('wf_plan_week')->nullable()->after('quantity');
            $table->string('pv_date_raw')->nullable()->after('wf_plan_week');
            $table->string('assets_status')->nullable()->after('pv_date_raw');
            $table->string('priority')->nullable()->after('assets_status');
            $table->string('wip')->nullable()->after('priority');
            $table->string('start_date_week')->nullable()->after('wip');
            $table->string('ready_to_check_week')->nullable()->after('start_date_week');
            $table->string('designer_name_raw')->nullable()->after('ready_to_check_week');
            $table->string('gpm_name_raw')->nullable()->after('designer_name_raw');
            $table->text('gpm_note')->nullable()->after('gpm_name_raw');
            $table->text('gd_notes')->nullable()->after('gpm_note');
            $table->string('job_number')->nullable()->after('gd_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'theme',
                'import_year',
                'batch',
                'artwork_type',
                'phase_task',
                'project_status_raw',
                'quantity',
                'wf_plan_week',
                'pv_date_raw',
                'assets_status',
                'priority',
                'wip',
                'start_date_week',
                'ready_to_check_week',
                'designer_name_raw',
                'gpm_name_raw',
                'gpm_note',
                'gd_notes',
                'job_number',
            ]);
        });
    }
};
