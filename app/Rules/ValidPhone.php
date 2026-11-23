<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validates the national number against the submitted dialling code (field `phone_country`). */
class ValidPhone implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function __construct(private string $countryField = 'phone_country') {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (PhoneNumber::normalise($this->data[$this->countryField] ?? null, is_string($value) ? $value : null) === null) {
            $fail('Enter a valid mobile number for the selected country code (digits only, without the country code).');
        }
    }
}
