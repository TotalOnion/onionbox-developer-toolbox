<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonRecipeValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'Recipe';
    protected const REQUIRED_FIELDS = [
        'name'               => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'description'        => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'image'              => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
        'recipeIngredient'   => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_ARRAY ],
        'recipeInstructions' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_ARRAY ],
        'recipeYield'        => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
    ];
}
