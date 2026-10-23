<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner, reusing an existing personal team.
     */
    public function handle(User $user, string $name, bool $isPersonal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            if (! $isPersonal) {
                return $this->createTeamWithOwner($user, $name, false);
            }

            $personal = $user->personalTeam();

            if ($personal !== null) {
                $user->switchTeam($personal);

                return $personal;
            }

            return $this->createTeamWithOwner($user, $name, true);
        });
    }

    /**
     * Create a team with the given name and make the user its owner.
     */
    protected function createTeamWithOwner(User $user, string $name, bool $isPersonal): Team
    {
        $team = Team::create([
            'name' => $name,
            'is_personal' => $isPersonal,
        ]);

        $team->memberships()->create([
            'user_id' => $user->id,
            'role' => TeamRole::Owner,
        ]);

        $user->switchTeam($team);

        return $team;
    }
}
