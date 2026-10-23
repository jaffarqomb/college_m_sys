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
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique('user_id');
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('employee_number', 30)->unique();
            $table->string('title', 40)->nullable();
            $table->string('office', 40)->nullable();
            $table->string('office_hours')->nullable();
            $table->boolean('is_head')->default(false);
            $table->timestamps();

            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructors');
    }
};
