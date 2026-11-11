<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonContactPointValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'ContactPoint';
    protected const REQUIRED_FIELDS = [
        'contactType' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
    ];
    protected const OPTIONAL_FIELDS = [
        'email'     => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'telephone' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
    ];

    public function validate():array {
        $errors = parent::validate();

        // We need either an email or a phone number
        if (
            ! $this->optional_fields['email']->is_valid()
            && ! $this->optional_fields['telephone']->is_valid()
        ) {
            $errors[] = 'ContactPoint Schemas need to have a means of contact; either email or telephone';
        }

        return $errors;
    }
}
