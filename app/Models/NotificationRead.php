<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One user has read one notification (read state is per user, not per center). */
class NotificationRead extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['notification_id', 'user_id', 'read_at'];
}
