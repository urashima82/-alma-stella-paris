<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\ProductCategory;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class LeafCategoryValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof LeafCategory) {
            throw new UnexpectedTypeException($constraint, LeafCategory::class);
        }

        if ($value === null) {
            return;
        }

        if (!$value instanceof ProductCategory) {
            return;
        }

        if ($value->hasChildren()) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
