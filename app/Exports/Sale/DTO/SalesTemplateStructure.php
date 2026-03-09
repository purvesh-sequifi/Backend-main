<?php

namespace App\Exports\Sale\DTO;

class SalesTemplateStructure
{
    public function __construct(
        public array $headers,
        public array $requiredColumnIndexes,
        public array $dateColumnIndexes
    ) {}
}
