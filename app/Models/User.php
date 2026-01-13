<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Rôles Utilisateurs */
    const ROLE_DEV = 'dev';
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_WEBMASTER = 'webmaster';
    const ROLE_STOCKIST = 'stockist';
    const ROLE_WAREHOUSE = 'warehouse';
    const ROLE_DELIVERY = 'delivery';
    const ROLE_DISTRIBUTOR = 'distributor';
    const ROLE_CONSUMER = 'consumer';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'role',
        'country',
        'supervisor_id',
        'email',
        'password',
        'phone',
        'bio',
        'gender',
        'address',
        'city',
        'image_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function wishlist(): HasOne
    {
        return $this->hasOne(Wishlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the supervisor (parent user)
     */
    public function supervisor(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'supervisor_id');
    }

    /**
     * Get the subordinates (managed users)
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'supervisor_id', 'id');
    }

    /**
     * Check if user is an administrator (Super Admin or Dev)
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_DEV, self::ROLE_SUPER_ADMIN]);
    }
}
