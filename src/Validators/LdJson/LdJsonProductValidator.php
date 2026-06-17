<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

class LdJsonProductValidator extends LdJsonValidator {

    protected const REQUIRED_FIELDS = [
        'name'  => LdJsonValidator::FIELD_TYPE_STRING,
        'brand' => LdJsonValidator::FIELD_TYPE_ARRAY,

    ];

    public function validate():array {
        $errors = parent::validate();
        return $errors;
    }
}
