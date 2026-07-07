<?php

namespace OnionWordpressDeveloperToolbox\Validators\FieldValidators;

use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;

class FieldValidatorString extends FieldValidatorAbstract {

    public function validate( mixed $value, array $flags = [] ):bool {
        if ( gettype( $value ) !== 'string' ) {
            throw new FieldValidatorException(
                sprintf( 'Field %s is expected to be a string, but is %s', $this->key, gettype( $value ) )
            );
        }

        return true;
    }
}
