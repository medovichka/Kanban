<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    protected $casts = [
        'id' => 'integer',
    ];

    public function displayName(): string
    {
        $name = trim((string) $this->name);

        return $name !== '' ? $name : (string) $this->email;
    }

    public function toApi(): array
    {
        return [
            'id' => (int) $this->getKey(),
            'name' => (string) $this->name,
            'email' => (string) $this->email,
        ];
    }
}
