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
        'address',
        'postcode', // Added postcode field
        'phone',
        'website',
        'latitude',
        'longitude',
        'category',
        'google_rating',
        'user_ratings_total'
    ];

    // Extract postcode from address when saving
    protected static function booted()
    {
        static::saving(function ($business) {
            // Try to extract Australian postcode (4 digits at the end of the address)
            if (!$business->postcode && $business->address) {
                preg_match('/\b(\d{4})\b(?![\w\d])/', $business->address, $matches);
                if (!empty($matches[1])) {
                    $business->postcode = $matches[1];
                }
            }
        });
    }

    // Scope to search by postcode
    public function scopeByPostcode($query, $postcode)
    {
        return $query->where('postcode', $postcode)
                    ->orWhere('address', 'LIKE', "%$postcode%");
    }
}
