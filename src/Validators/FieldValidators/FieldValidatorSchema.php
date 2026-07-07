<?php

namespace OnionWordpressDeveloperToolbox\Validators\FieldValidators;

use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;
use OnionWordpressDeveloperToolbox\Validators\LdJson\LdJsonValidatorFactory;

class FieldValidatorSchema extends FieldValidatorAbstract {

    public function validate( mixed $value, array $flags = [] ):bool {
        if ( ! is_array( $value ) ) {
            throw new FieldValidatorException(
                sprintf(
                    'Field %s cannot be checked as ld+json as the data passed was not an array, %s received instead',
                    $this->key,
                    gettype( $value )
                )
            );
        }

        $ld_json_validator = ( new LdJsonValidatorFactory )->instance( $value );
        $errors = $ld_json_validator->validate();

        if ( $errors ) {
            throw new FieldValidatorException(
                sprintf(
                    'Sub-field %s is expected to be an @%s, but has errors: "%s"',
                    $this->key,
                    implode( ', ', $errors )
                )
            );
        }

        return true;
    }
}
