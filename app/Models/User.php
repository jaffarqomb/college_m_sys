<?php

namespace App\Models;

use App\Actions\Teams\CreateTeam;
use App\Concerns\HasTeams;
use App\Enums\UserRole;
use App\Notifications\Channels\NotifiableByPhone;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string|null $phone_number
 * @property int|null $current_team_id
 * @property UserRole $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 */
#[Fillable(['name', 'email', 'password', 'current_team_id', 'role', 'phone_number'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail, NotifiableByPhone, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Boot the model and its global event hooks.
     */
    protected static function booted(): void
    {
        static::created(function (self $user): void {
            if ($user->personalTeam() === null) {
                app(CreateTeam::class)->handle($user, $user->name."'s Team", isPersonal: true);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role->canAccessPanel();
    }

    /**
     * Determine if the user is the system administrator.
     */
    public function isSystemAdmin(): bool
    {
        return $this->role === UserRole::SystemAdmin;
    }

    /**
     * Determine if the user is the registrar.
     */
    public function isRegistrar(): bool
    {
        return $this->role === UserRole::Registrar;
    }

    /**
     * Determine if the user is a department head.
     */
    public function isDepartmentHead(): bool
    {
        return $this->role === UserRole::DepartmentHead;
    }

    /**
     * Determine if the user is a finance officer.
     */
    public function isFinanceOfficer(): bool
    {
        return $this->role === UserRole::FinanceOfficer;
    }

    /**
     * Determine if the user holds any of the given roles.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Get the department the user manages as its head, if any.
     */
    public function managedDepartmentId(): ?int
    {
        return $this->isDepartmentHead()
            ? $this->instructorProfile()->firstWhere('is_head', true)?->department_id
            : null;
    }

    /**
     * Get the student profile record for this user.
     *
     * @return HasOne<Student, $this>
     */
    public function studentProfile()
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    /**
     * Get the instructor profile record for this user.
     *
     * @return HasOne<Instructor, $this>
     */
    public function instructorProfile()
    {
        return $this->hasOne(Instructor::class, 'user_id');
    }

    /**
     * Route for the custom "sms" notification channel.
     */
    public function routeNotificationForSms(?object $notification = null): ?string
    {
        return $this->phone_number;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Scope a query to active users (everyone outside teaching-only staff).
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('role', '!=', UserRole::Instructor->value);
    }
}
