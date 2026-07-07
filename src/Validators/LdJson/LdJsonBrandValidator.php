<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonBrandValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'Brand';
    protected const REQUIRED_FIELDS = [
        'name'  => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
    ];
}
