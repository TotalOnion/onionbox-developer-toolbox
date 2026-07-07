<?php

namespace OnionWordpressDeveloperToolbox\Validators\FieldValidators;

use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;
use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorAbstract;

class FieldValidatorFactory {
    private const VALIDATOR_BASE_CLASSNAME = 'OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidator';
    public const FIELD_TYPE_ARRAY   = 'Array';
    public const FIELD_TYPE_INT     = 'Int';
    public const FIELD_TYPE_SCHEMA  = 'Schema';
    public const FIELD_TYPE_STRING  = 'String';
    public const FIELD_TYPE_URL     = 'Url';
    public const VALID_FIELD_TYPES  = [
        self::FIELD_TYPE_ARRAY,
        self::FIELD_TYPE_INT,
        self::FIELD_TYPE_SCHEMA,
        self::FIELD_TYPE_STRING,
        self::FIELD_TYPE_URL,
    ];

    public static function instance(
        string $field_key,
        array $field_config
    ) : FieldValidatorAbstract
    {
        if ( ! array_key_exists( 'field_type', $field_config ) ) {
            throw new FieldValidatorException( sprintf( 'Invalid field configuration for key %s', $field_key ) );
        }

        if ( ! in_array( $field_config['field_type'], self::VALID_FIELD_TYPES ) ) {
            throw new FieldValidatorException(
                sprintf(
                    'Unknown FieldValidator type of %s. Valid types are %s',
                    $field_config['field_type'],
                    implode( ', ', self::VALID_FIELD_TYPES )
                )
            );
        }

        $classname = sprintf( '%s%s', self::VALIDATOR_BASE_CLASSNAME, $field_config['field_type'] );
        if ( ! class_exists( $classname ) ) {
            throw new FieldValidatorException( sprintf( 'No valid loadable class found for %s', $classname ) );
        }

        return new $classname( $field_key, $field_config );
    }
}
