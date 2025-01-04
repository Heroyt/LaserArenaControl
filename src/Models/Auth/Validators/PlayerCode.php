<?php

namespace App\Models\Auth\Validators;

use App\Models\Auth\Player;
use Attribute;
use Lsr\ObjectValidation\Attributes\Validator;
use Lsr\ObjectValidation\Exceptions\ValidationException;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class PlayerCode implements Validator
{
    /**
     * Validate a value and throw an exception on error
     *
     * @param  mixed  $value
     * @param  Player|class-string<Player>  $class
     * @param  string  $property
     *
     * @return void
     *
     * @throws ValidationException
     */
    public function validateValue(
      mixed           $value,
      object | string $class,
      string          $property,
      string          $propertyPrefix = ''
    ) : void {
        assert($class instanceof Player);
        $class::validateCode($value, $class, $propertyPrefix);
    }
}
