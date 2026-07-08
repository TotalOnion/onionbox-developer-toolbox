<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonRecipeValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'Recipe';
    protected const REQUIRED_FIELDS = [
        '@id'                => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
        'name'               => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'description'        => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'image'              => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
        'recipeIngredient'   => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_ARRAY ],
        'recipeInstructions' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_ARRAY ],
        'recipeYield'        => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING_INT ],
    ];
    protected const OPTIONAL_FIELDS = [
        'aggregateRating' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'AggregateRating' ],
    ];
}
