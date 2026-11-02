<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'type', 'amount_satang', 'description', 'transaction_date'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_satang' => 'integer',
            'transaction_date' => 'immutable_date',
        ];
    }

    /** @return Attribute<never, string|DateTimeInterface> */
    protected function transactionDate(): Attribute
    {
        return Attribute::set(fn (string|DateTimeInterface $value): string => CarbonImmutable::parse($value)->toDateString());
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
