<?php

namespace OnionWordpressDeveloperToolbox\Validators\FieldValidators;

use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;

abstract class FieldValidatorAbstract {

    public function __construct(
        public readonly string $key,
        protected readonly array $config,
    ) {}

    public function validate( mixed $value, array $flags = [] ):bool {
        throw new FieldValidatorException(
            sprintf( 'Child class %s does not correctly implement the validate() method for key %s',
                get_class( $this ),
                $this->key
            )
        );
    }
}
