<?php

namespace OnionWordpressDeveloperToolbox\Validators\FieldValidators;

use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;

class FieldValidatorArray extends FieldValidatorAbstract {

    public function validate( mixed $value, array $flags = [] ):bool {
        if ( gettype( $value ) !== 'int' ) {
            throw new FieldValidatorException(
                sprintf( 'Field %s is expected to be an int, but is %s', $this->key, gettype( $value ) )
            );
        }

        return $this->has_passed_validation = true;
    }
}
