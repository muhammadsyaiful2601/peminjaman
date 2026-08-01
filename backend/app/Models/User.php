<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        ];
    }

    public function createdLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'created_by');
    }

    public function verifiedLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'verified_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isStaff(): bool
    {
        return $this->role === 'admin' || $this->role === 'assistant';
    }
}
