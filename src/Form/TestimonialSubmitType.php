<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Testimonial;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/** @extends AbstractType<Testimonial> */
class TestimonialSubmitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'choices' => [
                    '1' => 1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ],
                'expanded' => true,
                'constraints' => [
                    new NotBlank(message: 'testimonial.error.rating_required'),
                    new Range(min: 1, max: 5),
                ],
            ])
            ->add('text', TextareaType::class, [
                'constraints' => [
                    new NotBlank(message: 'testimonial.error.text_required'),
                    new Length(min: 10, max: 2000, minMessage: 'testimonial.error.text_too_short'),
                ],
            ])
            ->add('firstName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'testimonial.error.first_name_required'),
                    new Length(max: 100),
                ],
            ])
            ->add('lastNameInitial', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'testimonial.error.last_name_initial_required'),
                    new Length(max: 1),
                ],
            ])
            ->add('city', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Length(max: 255),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Testimonial::class,
        ]);
    }
}
