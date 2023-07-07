<?php

namespace Lester\EloquentSalesForce\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lester\EloquentSalesForce\Traits\SyncsWithSalesforce;
use Illuminate\Support\Str;

class TestModel extends Model
{
    use SyncsWithSalesforce;

    protected $salesForceObject = 'Lead';

    protected $table = 'leads';

    protected $fillable = [
        'email',
        'name',
        'company'
    ];

    protected $salesForceFieldMap = [
        'Email' => 'email',
        'FirstName' => 'first_name',
        'LastName' => 'last_name',
        'Company' => 'company',
    ];

    public function getFirstNameAttribute()
    {
        return Str::before($this->name, ' ');
    }

    public function getLastNameAttribute()
    {
        return Str::after($this->name, ' ');
    }

    public function setFirstNameAttribute($value)
    {
        $nameParts = explode(' ', $this->attributes['name']);
        $nameParts[0] = $value;
        $this->attributes['name'] = implode(' ', $nameParts);
    }

    public function setLastNameAttribute($value)
    {
        $nameParts = explode(' ', $this->attributes['name']);
        $nameParts[1] = $value;
        $this->attributes['name'] = implode(' ', $nameParts);
    }
}
