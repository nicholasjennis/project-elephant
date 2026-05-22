<?php

use App\Models\Task;
use App\Models\User;

it('redirects guests away from tasks page', function () {
    $this->get(route('tasks.index'))->assertRedirect(route('login'));
});

it('allows authenticated users to view tasks page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks.index'))
        ->assertOk();
});

it('allows gpm users to create a task with multiple designers', function () {
    $gpm = User::factory()->create(['role' => 'gpm']);
    $designerA = User::factory()->create(['role' => 'designer']);
    $designerB = User::factory()->create(['role' => 'designer']);

    $this->actingAs($gpm)
        ->post(route('tasks.store'), [
            'sku' => 'SKU-5000',
            'title' => 'Campaign landing page',
            'description' => 'Prepare assets and variants.',
            'status' => 'in_progress',
            'due_date' => now()->addDays(10)->toDateString(),
            'designer_ids' => [$designerA->id, $designerB->id],
        ])
        ->assertRedirect();

    $task = Task::where('sku', 'SKU-5000')->first();

    expect($task)->not->toBeNull();
    expect($task?->gpm_user_id)->toBe($gpm->id);
    expect($task?->designers()->pluck('users.id')->all())->toEqualCanonicalizing([$designerA->id, $designerB->id]);
});

it('prevents designer users from creating tasks', function () {
    $designer = User::factory()->create(['role' => 'designer']);
    $otherDesigner = User::factory()->create(['role' => 'designer']);

    $this->actingAs($designer)
        ->post(route('tasks.store'), [
            'sku' => 'SKU-6000',
            'title' => 'Unauthorized attempt',
            'status' => 'new',
            'designer_ids' => [$otherDesigner->id],
        ])
        ->assertForbidden();
});

it('filters tasks by sku and status', function () {
    $user = User::factory()->create(['role' => 'designer']);

    Task::factory()->create(['sku' => 'SKU-ALPHA', 'status' => 'new']);
    Task::factory()->create(['sku' => 'SKU-BETA', 'status' => 'done']);

    $response = $this->actingAs($user)
        ->get(route('tasks.index', ['sku' => 'ALPHA', 'status' => 'new']));

    $response->assertOk();
    $response->assertSee('SKU-ALPHA');
    $response->assertDontSee('SKU-BETA');
});

it('allows gpm users to update task status', function () {
    $gpm = User::factory()->create(['role' => 'gpm']);
    $task = Task::factory()->create(['status' => 'new']);

    $this->actingAs($gpm)
        ->patch(route('tasks.update-status', $task), ['status' => 'done'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('done');
});

it('prevents designer users from updating task status', function () {
    $designer = User::factory()->create(['role' => 'designer']);
    $task = Task::factory()->create(['status' => 'new']);

    $this->actingAs($designer)
        ->patch(route('tasks.update-status', $task), ['status' => 'done'])
        ->assertForbidden();
});
