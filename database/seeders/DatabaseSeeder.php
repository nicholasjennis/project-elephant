<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $gpm = User::factory()->create([
            'name' => 'GPM User',
            'email' => 'gpm@example.com',
            'role' => 'gpm',
        ]);

        $designerA = User::factory()->create([
            'name' => 'Designer One',
            'email' => 'designer1@example.com',
            'role' => 'designer',
        ]);

        $designerB = User::factory()->create([
            'name' => 'Designer Two',
            'email' => 'designer2@example.com',
            'role' => 'designer',
        ]);

        $task = Task::create([
            'sku' => 'SKU-1001',
            'phase_task' => 'Homepage hero refresh',
            'description' => 'Update hero banner visuals and messaging for summer campaign.',
            'gpm_user_id' => $gpm->id,
            'project_status' => 'Upcoming',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $task->designers()->sync([$designerA->id, $designerB->id]);
    }
}
