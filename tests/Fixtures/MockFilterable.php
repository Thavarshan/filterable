<?php

namespace Filterable\Tests\Fixtures;

use Filterable\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $name
 * @property string|null $email
 * @property string|null $status
 * @property int|null $age
 * @property bool $is_visible
 */
class MockFilterable extends Model
{
    use Filterable;
    /** @use HasFactory<MockFilterableFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mocks';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
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
    protected static function newFactory(): MockFilterableFactory
    {
        return MockFilterableFactory::new();
    }
}
