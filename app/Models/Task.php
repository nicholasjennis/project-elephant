<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'sku',
    'description',
    'theme',
    'import_year',
    'batch',
    'artwork_type',
    'phase_task',
    'project_status',
    'quantity',
    'wf_plan_week',
    'pv_date_raw',
    'assets_status',
    'priority',
    'wip',
    'start_date_week',
    'ready_to_check_week',
    'gpm_note',
    'gd_notes',
    'job_number',
    'gpm_user_id',
    'due_date',
])]
class Task extends Model
{
    use HasFactory;

    public function gpm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gpm_user_id');
    }

    public function designers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_designer')->withTimestamps();
    }
}
