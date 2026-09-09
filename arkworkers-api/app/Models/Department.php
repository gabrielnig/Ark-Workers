<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * The roles available for this department to assign to a member,
     * a subset of the master Role list, toggled by an admin.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Workers who belong to this department, with their role in it
     * available via the pivot.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(DepartmentUser::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }
}
