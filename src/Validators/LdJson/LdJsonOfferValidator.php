<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonOfferValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'Offer';
    protected const REQUIRED_FIELDS = [
        'priceCurrency' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'price'         => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
    ];
}
