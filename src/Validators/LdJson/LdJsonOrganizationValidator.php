<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonOrganizationValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'Organization';
    protected const REQUIRED_FIELDS = [
        'name'               => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'description'        => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'url'                => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
    ];
    protected const OPTIONAL_FIELDS = [
        'contactPoint' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_ARRAY, 'array_element_type' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'ContactPoint' ] ],
        'logo'         => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
        'sameAs'       => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_ARRAY, 'array_element_type' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ] ],
    ];
}
