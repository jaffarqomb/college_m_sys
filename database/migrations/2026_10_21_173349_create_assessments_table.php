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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20)->default('quiz')->index();
            $table->decimal('weight', 5, 2)->default(10.00);
            $table->decimal('max_score', 6, 2)->default(100.00);
            $table->date('due_date')->nullable();
            $table->date('conducted_at')->nullable();
            $table->timestamps();

            $table->index('section_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
