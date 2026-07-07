<?php

namespace OnionWordpressDeveloperToolbox\Validators\FieldValidators;

use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;

class FieldValidatorArray extends FieldValidatorAbstract {

    public function validate( mixed $value, array $flags = [] ):bool {
        if ( gettype( $value ) !== 'array' ) {
            throw new FieldValidatorException(
                sprintf( 'Field %s is expected to be an array, but is %s', $this->key, gettype( $value ) )
            );
        }

        return true;
    }
}
