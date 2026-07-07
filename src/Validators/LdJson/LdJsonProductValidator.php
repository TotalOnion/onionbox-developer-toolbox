<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonProductValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'Product';
    protected const REQUIRED_FIELDS = [
        '@id'   => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
        'name'  => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'brand' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'Brand' ],
        'image' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
    ];
}
