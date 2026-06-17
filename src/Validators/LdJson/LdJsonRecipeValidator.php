<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

class LdJsonRecipeValidator extends LdJsonValidator {

    protected const REQUIRED_FIELDS = [
        'image'              => LdJsonValidator::FIELD_TYPE_URL,
        'recipeIngredient'   => LdJsonValidator::FIELD_TYPE_ARRAY,
        'recipeInstructions' => LdJsonValidator::FIELD_TYPE_ARRAY,

    ];

    public function validate():array {
        $errors = parent::validate();
        return $errors;
    }
}
