<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class MaxCategoryDepth extends Constraint
{
    public string $message = 'A subcategory cannot itself have children. Maximum depth is 2 levels.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
