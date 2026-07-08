<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonWebPageValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'WebPage';
    protected const REQUIRED_FIELDS = [
        'name'  => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'url'   => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
    ];
}
