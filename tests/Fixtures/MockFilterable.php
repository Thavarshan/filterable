<?php

namespace Filterable\Tests\Fixtures;

use Filterable\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockFilterable extends Model
{
    use Filterable, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mocks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'status',
        'age',
        'is_visible',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return MockFilterableFactory::new();
    }
}
