<?php

namespace App\Imports\Sales\DTO;

class ImportFieldMeta
{
    public function __construct(
        public ?string $name = null,
        public string $type = 'text',
        public bool $mandatory = false,
        public bool $fromDb = false,
        public bool $isCustom = false,

    ) {}

    public function isMandatory(): bool
    {
        return $this->mandatory;
    }
}
