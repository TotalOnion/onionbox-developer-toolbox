<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

class LdJsonArticleValidator extends AbstractLdJsonValidator {
    protected const SCHEMA_NAME = 'Article';
    protected const REQUIRED_FIELDS = [
        'headline'         => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_STRING ] ],
        'mainEntityOfPage' => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'WebPage' ] ],
        'image'            => [ 
            [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ],
            [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'ImageObject' ],
        ],
        'author'           => [ 
            [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'Person' ],
            [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_SCHEMA, 'schema' => 'Organization' ],
        ],
    ];

    protected const OPTIONAL_FIELDS = [
        'url'   => [ [ 'field_type' => FieldValidatorFactory::FIELD_TYPE_URL ] ],
    ];
}
