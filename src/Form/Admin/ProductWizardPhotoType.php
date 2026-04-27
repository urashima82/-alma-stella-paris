<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Enum\PhotoAngle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<ProductWizardPhotoData> */
class ProductWizardPhotoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'class' => 'wizard-photo-input',
                ],
            ])
            ->add('angle', EnumType::class, [
                'label' => 'Angle',
                'class' => PhotoAngle::class,
                'choice_label' => static fn (PhotoAngle $angle): string => $angle->label(),
                'attr' => ['class' => 'wizard-photo-angle'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductWizardPhotoData::class,
        ]);
    }
}
