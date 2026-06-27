<?php

namespace App\Models;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Orbit\Concerns\Orbital;

class User extends Authenticatable
{
    use Orbital;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
    ];

    public $incrementing = false;

    public function getKeyName()
    {
        return 'username';
    }

    public function getIncrementing()
    {
        return false;
    }

    public static function schema(Blueprint $table)
    {
        $table->string('username');
        $table->string('password');
        $table->timestamp('connected_at')->nullable();
    }

    public function bundles()
    {
        return $this->hasMany(Bundle::class);
    }
}
