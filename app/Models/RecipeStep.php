<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeStep extends Model
{
    protected $fillable = [
        'recipe_id',
        'step_no',
        'instruction',
        'duration_minutes',
        'media_url',
    ];

    protected $casts = [
        'step_no'          => 'integer',
        'duration_minutes' => 'integer',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
