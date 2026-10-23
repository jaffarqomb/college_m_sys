<?php

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Courses\Pages\EditCourse;
use App\Filament\Admin\Resources\Courses\RelationManagers\PrerequisitesRelationManager;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('edit page renders the prerequisites relation manager for the registrar', function () {
    actingAs(User::factory()->asRole(UserRole::Registrar)->create());

    $course = Course::factory()->create();
    $prerequisite = Course::factory()->create();

    $course->prerequisites()->attach($prerequisite);

    Livewire::test(PrerequisitesRelationManager::class, [
        'ownerRecord' => $course,
        'pageClass' => EditCourse::class,
    ])
        ->assertSuccessful()
        ->assertSee($prerequisite->code)
        ->assertSee($prerequisite->title);
});
