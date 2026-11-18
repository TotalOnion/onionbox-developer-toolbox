<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonPersonValidator extends AbstractLdJsonValidator {
    protected const SCHEMA_NAME = 'Person';
    protected const REQUIRED_FIELDS = [
        'name'  => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ] ],
    ];

    protected const OPTIONAL_FIELDS = [
        'url'   => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ] ],
    ];
}
