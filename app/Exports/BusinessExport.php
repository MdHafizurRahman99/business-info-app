<?php

namespace App\Exports;

use App\Models\Business;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BusinessExport implements FromCollection, WithHeadings
{
    protected $googleRating;

    public function __construct($googleRating)
    {
        $this->googleRating = $googleRating;
    }

    public function collection()
    {
        return Business::where('google_rating', '<=', $this->googleRating)->get();
    }

    public function headings(): array
    {
        return [
            'place_id',
            'name',
            'address',
            'postcode',
            'phone',
            'website',
            'latitude',
            'longitude',
            'category',
            'google_rating',
            'user_ratings_total'
        ];
    }
}
