<?php

namespace OnionWordpressDeveloperToolbox\Validators\FieldValidators;

use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;

class FieldValidatorStringFloat extends FieldValidatorAbstract {

    public function validate( mixed $value, array $flags = [] ):bool {
        // NB: numbers that are floats in the source, but happen to be whole numbers, get typed as ints, so this validator is basically "is numeric"
        if ( ! in_array( gettype( $value ), [ 'string', 'double', 'integer' ] ) ) {
            throw new FieldValidatorException(
                sprintf( 'Field %s is expected to be a string or an float, but is %s', $this->key, gettype( $value ) )
            );
        }

        return $this->has_passed_validation = true;
    }
}
