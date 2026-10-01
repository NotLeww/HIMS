<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAvatar extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'mime_type', 'content'];

    protected $hidden = ['content'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
