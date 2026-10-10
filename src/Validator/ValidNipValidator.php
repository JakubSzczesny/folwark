<?php

declare(strict_types=1);

namespace App\Validator;

use App\Util\Nip;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidNipValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidNip) {
            throw new UnexpectedTypeException($constraint, ValidNip::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!Nip::isValid((string) $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
