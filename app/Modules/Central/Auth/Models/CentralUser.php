<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_global_admin', 'locked_until'])]
#[Hidden(['password', 'remember_token'])]
class CentralUser extends Authenticatable {
    use HasFactory, HasUuids, Notifiable;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \App\Modules\Central\Auth\Database\Factories\CentralUserFactory
    {
        return \App\Modules\Central\Auth\Database\Factories\CentralUserFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_global_admin' => 'boolean',
            'locked_until' => 'datetime',
        ];
    }

    public function twoFactorAuth(): HasOne
    {
        return $this->hasOne(Central2FA::class, 'user_id');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->twoFactorAuth()->exists();
    }

    /**
     * Envía la notificación de restablecimiento de contraseña.
     *
     * @param string $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Modules\Central\Auth\Notifications\CentralResetPasswordNotification($token));
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return \Illuminate\Support\Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => \Illuminate\Support\Str::substr($word, 0, 1))
            ->implode('');
    }
}
