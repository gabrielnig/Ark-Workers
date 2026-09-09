<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'grants_management'];

    protected function casts(): array
    {
        return [
            'grants_management' => 'boolean',
        ];
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }
}
