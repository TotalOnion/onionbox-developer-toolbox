<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonWebPageValidator extends AbstractLdJsonValidator {
    protected const SCHEMA_NAME = 'WebPage';
    protected const REQUIRED_FIELDS = [
        'url'   => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ] ],
    ];

    protected const OPTIONAL_FIELDS = [
        'name'  => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ] ],
    ];

    public function validate():array {
        $errors = parent::validate();

        // If the schema we are evaluating is the top schema, then the URL is required. 
        if ( ! $this->is_sub_schema ) {
            if (
                ! $this->field_validator_is_valid( $this->optional_fields['name'] )
            ) {
                $errors[] = 'WebPage Schemas that are not the child of a parent Schema must have a `name` field';
            }
        }

        return $errors;
    }
}
