<?php

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('only the system admin sees the new-team option in the team switcher', function (UserRole $role, bool $shouldSeeTrigger) {
    $user = User::factory()->asRole($role)->create();

    actingAs($user);

    Livewire::test('team-switcher')
        ->{$shouldSeeTrigger ? 'assertSee' : 'assertDontSee'}('New team');
})->with([
    [UserRole::SystemAdmin, true],
    [UserRole::Registrar, false],
    [UserRole::Student, false],
]);

test('a system admin can create a team from the dashboard modal', function () {
    $user = User::factory()->asRole(UserRole::SystemAdmin)->create();

    actingAs($user);

    $teamsBefore = Team::count();

    Livewire::test('create-team-modal')
        ->set('teamName', 'Deploy Squad')
        ->call('createTeam')
        ->assertHasNoErrors();

    expect(Team::count())->toBe($teamsBefore + 1)
        ->and(Team::query()->where('name', 'Deploy Squad')->exists())->toBeTrue();
});

test('non-system-admins cannot create teams even when the modal is submitted directly', function (UserRole $role) {
    $user = User::factory()->asRole($role)->create();

    actingAs($user);

    $teamsBefore = Team::count();

    Livewire::test('create-team-modal')
        ->set('teamName', 'Rogue Team')
        ->call('createTeam');

    // The modal aborts with 403 before the create action ever runs, so no
    // team is created and no error/validation state leaks to the user.
    expect(Team::count())->toBe($teamsBefore)
        ->and(Team::query()->where('name', 'Rogue Team')->exists())->toBeFalse();
})->with([
    UserRole::Student,
    UserRole::Instructor,
    UserRole::Registrar,
    UserRole::DepartmentHead,
    UserRole::FinanceOfficer,
]);
