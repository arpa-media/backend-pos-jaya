<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Outlet extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'timezone',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
