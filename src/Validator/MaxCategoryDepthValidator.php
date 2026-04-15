<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\ProductCategory;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class MaxCategoryDepthValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MaxCategoryDepth) {
            throw new UnexpectedTypeException($constraint, MaxCategoryDepth::class);
        }

        if (!$value instanceof ProductCategory) {
            throw new UnexpectedValueException($value, ProductCategory::class);
        }

        $parent = $value->getParent();

        if ($parent === null) {
            return;
        }

        // A category with a parent cannot have a grandparent (max 2 levels)
        if ($parent->getParent() !== null) {
            $this->context->buildViolation($constraint->message)
                ->atPath('parent')
                ->addViolation();
        }
    }
}
