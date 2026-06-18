<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

class LdJsonProductValidator extends LdJsonValidator {

    protected const REQUIRED_FIELDS = [
        'name'  => LdJsonValidator::FIELD_TYPE_STRING,
        'brand' => LdJsonValidator::FIELD_TYPE_ARRAY,
        'image' => LdJsonValidator::FIELD_TYPE_URL,
    ];
}
