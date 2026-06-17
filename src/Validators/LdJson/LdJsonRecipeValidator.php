<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

class LdJsonRecipeValidator extends LdJsonValidator {

    public function validate() {
        $errors = $this->validate_json_ld_node( $this->ld_json );
        return $errors;
    }
}
