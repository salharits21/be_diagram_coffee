<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',             
        'loyalty_points',
        'branch_id',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Cabang tempat admin bertugas (nullable, hanya untuk role admin).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Cek apakah user adalah super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Cek apakah user adalah admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Cek apakah user adalah customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Pesanan milik user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Voucher yang dimiliki user (pivot relation).
     */
    public function vouchers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Voucher::class, 'user_vouchers')
            ->withPivot('id', 'is_used', 'used_at', 'expired_at')
            ->withTimestamps()
            ->using(UserVoucher::class);
    }

    /**
     * Pivot data untuk voucher.
     */
    public function userVouchers(): HasMany
    {
        return $this->hasMany(UserVoucher::class, 'user_id');
    }
}