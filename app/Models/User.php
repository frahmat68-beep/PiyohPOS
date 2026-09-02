<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'active_outlet_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->hasRole('super_admin') || $this->hasRole('admin')) {
            return true;
        }

        if ($panel->getId() === 'cashier' && $this->hasRole('cashier')) {
            return true;
        }

        if ($panel->getId() === 'kitchen' && $this->hasRole('kitchen')) {
            return true;
        }

        return false;
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'active_outlet_id',
    ];

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
            'active_outlet_id' => 'integer',
        ];
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'active_outlet_id');
    }
}
