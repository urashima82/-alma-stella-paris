<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class LeafCategory extends Constraint
{
    public string $message = 'Products can only be assigned to leaf categories (subcategories or parents without children).';
}
