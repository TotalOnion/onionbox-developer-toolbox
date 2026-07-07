<?php

namespace OnionWordpressDeveloperToolbox\Validators\FieldValidators;

use WP_Http;
use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;
use OnionWordpressDeveloperToolbox\Services\HttpService;

class FieldValidatorUrl extends FieldValidatorAbstract {

    public function validate( mixed $value, array $flags = [] ):bool {
        if ( ! HttpService::is_target_url_valid( $value ) ) {
            throw new FieldValidatorException(
                sprintf(
                    'Field "%s" is not a valid URL',
                    $this->key
                )
            );
        }

        if( $flags['follow-links'] ?? false ) {
            $http_service = new HttpService();

            $response = $http_service->get( $value );
            if ( is_wp_error( $response ) ) {
                throw new FieldValidatorException(
                    sprintf(
                        'Field "%s" caused an error when tested; "%s"',
                        $this->key,
                        $response->get_error_message()
                    )
                );
            }

            if( $response['response']['code'] !== WP_Http::OK ) {
                throw new FieldValidatorException(
                    sprintf(
                        'Field "%s", "%s" has a non 200 http response code. received %s',
                        $this->key,
                        $value,
                        $response['response']['code']
                    )
                );
            }
        }

        return true;
    }
}
