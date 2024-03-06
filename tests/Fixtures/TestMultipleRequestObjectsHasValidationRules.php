<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class TestMultipleRequestObjectsHasValidationRules extends FormRequest
{
    public function rules()
    {
        return [
            'user_id' => 'int|required',
            'room_id' => ['string'],
            'forever' => 'boolean',
            'no_example_attribute' => 'numeric',
            'another_one' => 'numeric',
            'even_more_param' => 'array',
            'book.name' => 'string',
            'book.author_id' => 'integer',
            'book.pages_count' => 'integer',
            'ids.*' => 'integer',
            'users.*.first_name' => ['string'],
            'users.*.last_name' => 'string',
        ];
    }
}
