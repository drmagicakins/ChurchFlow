<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'monthly_price', 'yearly_price', 'currency',
        'max_members', 'max_branches', 'max_admins', 'storage_mb',
        'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function priceFor(string $billingInterval): string
    {
        return $billingInterval === 'yearly' && $this->yearly_price !== null
            ? (string) $this->yearly_price
            : (string) $this->monthly_price;
    }

    public function hasFeature(string $key): bool
    {
        return (bool) ($this->features[$key] ?? false);
    }
}
