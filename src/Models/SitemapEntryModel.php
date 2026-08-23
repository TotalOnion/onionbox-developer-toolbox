<?php

namespace OnionWordpressDeveloperToolbox\Models;

use DateTimeImmutable;

class SitemapEntryModel {
    public function __construct(
        public readonly string $loc,
        public readonly ?DateTimeImmutable $lastmod
    ){}
}
