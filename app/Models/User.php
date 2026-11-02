<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function getJWTIdentifier(): int
    {
        return $this->id;
    }

    /** @return array{ver: string, renew_until: int} */
    public function getJWTCustomClaims(): array
    {
        return [
            'ver' => $this->jwtFingerprint(),
            'renew_until' => now()->addDays(7)->getTimestamp(),
        ];
    }

    public function jwtFingerprint(): string
    {
        return hash_hmac('sha256', $this->getAuthPassword(), (string) config('app.key'));
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
        ];
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function createDefaultCategories(): void
    {
        $categories = [
            'expense' => ['อาหาร', 'การเดินทาง', 'ช้อปปิ้ง', 'บิลและค่าสาธารณูปโภค', 'ความบันเทิง', 'สุขภาพ', 'อื่น ๆ'],
            'income' => ['เงินเดือน', 'งานอิสระ', 'ของขวัญ', 'อื่น ๆ'],
        ];

        foreach ($categories as $type => $names) {
            foreach ($names as $name) {
                $this->categories()->firstOrCreate(['type' => $type, 'name' => $name]);
            }
        }
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
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
}
