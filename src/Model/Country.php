<?php

namespace App\Model;

use Symfony\Component\Intl\Countries;

final class Country
{
    private string $code;

    public function __construct(string $code)
    {
        $upperCode = strtoupper(trim($code));

        if (!Countries::exists($upperCode)) {
            throw new \InvalidArgumentException(sprintf('Invalid country code "%s".', $code));
        }

        $this->code = $upperCode;
    }

    public function getName(): string
    {
        return Countries::getName($this->code);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
