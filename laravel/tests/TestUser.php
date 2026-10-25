<?php

namespace MetaForge\Discord\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class TestUser extends Model
{
    use HasRoles;

    protected $table = 'test_users';

    protected $fillable = ['name', 'email'];

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(TestSocialAccount::class, 'user_id');
    }
}
