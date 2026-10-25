<?php

namespace MetaForge\Discord\Tests;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class TestUser extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasRoles;

    protected $guard_name = 'web';

    protected $table = 'test_users';

    protected $fillable = ['name', 'email'];

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(TestSocialAccount::class, 'user_id');
    }
}
