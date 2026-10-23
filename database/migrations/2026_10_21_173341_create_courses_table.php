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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('title');
            $table->unsignedTinyInteger('credits')->default(3);
            $table->unsignedTinyInteger('year_level')->default(1);
            $table->text('description')->nullable();
            $table->boolean('requires_lab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
