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

        // If no array_element_type is set, then we only care that it's an array
        if ( ! ( $this->config['array_element_type'] ?? false ) ) {
            return $this->has_passed_validation = true;
        }

        // Otherwise, check the array elements are of the correct type
        $element_validator = FieldValidatorFactory::instance(
            sprintf( '[%s]', $this->key ),
            $this->config['array_element_type']
        );

        foreach( $value as $array_element ) {
            $element_validator->validate( $array_element, $flags );
        }


        return $this->has_passed_validation = true;
    }
}
