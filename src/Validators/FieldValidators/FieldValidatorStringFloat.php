<?php

namespace OnionWordpressDeveloperToolbox\Validators\FieldValidators;

use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;

class FieldValidatorStringInt extends FieldValidatorAbstract {

    public function validate( mixed $value, array $flags = [] ):bool {
        if ( gettype( $value ) !== 'string' && gettype( $value ) !== 'double' ) {
            throw new FieldValidatorException(
                sprintf( 'Field %s is expected to be a string or an float, but is %s', $this->key, gettype( $value ) )
            );
        }

        if ( ! ( (float)$value ) ) {
            throw new FieldValidatorException(
                sprintf( 'Field %s is expected to be a string or a float, but is falsey when cast', $this->key )
            );
        }

        return $this->has_passed_validation = true;
    }
}
