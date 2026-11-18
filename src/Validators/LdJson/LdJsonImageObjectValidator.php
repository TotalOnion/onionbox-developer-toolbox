<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonImageObjectValidator extends AbstractLdJsonValidator {
    protected const SCHEMA_NAME = 'ImageObject';
    protected const REQUIRED_FIELDS = [
        'url'   => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ] ],
    ];
}
