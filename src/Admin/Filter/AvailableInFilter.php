<?php

declare(strict_types=1);

namespace App\Admin\Filter;

use App\Entity\Product;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class AvailableInFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label ?? 'Disponible en')
            ->setFormType(ChoiceType::class)
            ->setFormTypeOption('choices', [
                'France' => Product::COUNTRY_FRANCE,
                'Mexique' => Product::COUNTRY_MEXICO,
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $country = $filterDataDto->getValue();

        if (null === $country || '' === $country) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $paramName = 'available_in_country';

        $queryBuilder
            ->andWhere(\sprintf('JSON_CONTAINS(%s.availableIn, :%s) = 1', $alias, $paramName))
            ->setParameter($paramName, \sprintf('"%s"', $country));
    }
}
