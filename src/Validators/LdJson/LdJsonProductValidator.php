<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonProductValidator extends LdJsonValidator {
    protected const SCHEMA_NAME = 'Product';
    protected const REQUIRED_FIELDS = [
        '@id'   => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
        'name'  => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ],
        'brand' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'Brand' ],
        'image' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
    ];
    protected const OPTIONAL_FIELDS = [
        'offers'          => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'Offer' ],
        'aggregateRating' => [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'AggregateRating' ],
    ];

    public function validate():array {
        $errors = parent::validate();

        // If the Product schema we are evaluating is the top schema (ie, not part of a WebPage) check we have either offers or aggregateRating
        if ( ! $this->is_sub_schema ) {
            if (
                ! $this->optional_fields['offers']->is_valid()
                && ! $this->optional_fields['aggregateRating']->is_valid()
            ) {
                $errors[] = 'Product Schemas that are not the child of a parent Schema like WebPage should include either a valid "offers" or "aggregateRating" (or both)';
            }
        }

        return $errors;
    }
}
