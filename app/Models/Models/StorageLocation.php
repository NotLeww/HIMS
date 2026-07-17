<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Model;

class StorageLocation extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'zone',
        'capacity',
        'status',
    ];
}
