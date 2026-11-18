<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonBrandValidator extends AbstractLdJsonValidator {
    protected const SCHEMA_NAME = 'Brand';
    protected const REQUIRED_FIELDS = [
        'name'  => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ] ],
    ];
    protected const OPTIONAL_FIELDS = [
        'subjectOf' => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'WebPage' ] ],
    ];
}
