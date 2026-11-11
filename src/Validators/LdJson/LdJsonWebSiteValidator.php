<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonWebSiteValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'WebSite';
    protected const REQUIRED_FIELDS = [
        'name'               => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'description'        => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'url'                => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
    ];
}
