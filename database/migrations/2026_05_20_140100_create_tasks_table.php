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
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('gpm_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('new')->index();
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
