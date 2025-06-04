<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'place_id',
        'name',
        'category',
        'address',
        'phone',
        'website',
        'email',
        'google_rating',
        'user_ratings_total',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'google_rating' => 'decimal:1',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'user_ratings_total' => 'integer',
    ];

    /**
     * Get categories as an array
     */
    public function getCategoriesAttribute()
    {
        return $this->category ? explode(', ', $this->category) : [];
    }

    /**
     * Set categories from an array
     */
    public function setCategoriesAttribute($value)
    {
        $this->attributes['category'] = is_array($value) ? implode(', ', $value) : $value;
    }
}
