<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonAggregateRatingValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'AggregateRating';
    protected const REQUIRED_FIELDS = [
        'ratingValue' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRINGFLOAT ],
        'ratingCount' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRINGINT ],
        'bestRating'  => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRINGINT ],
    ];
}
