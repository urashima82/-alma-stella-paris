<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContactMessage;
use App\Enum\ContactSubject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<ContactMessage> */
class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'contact.error.name_required'),
                    new Length(max: 255),
                ],
            ])
            ->add('email', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'contact.error.email_required'),
                    new Email(message: 'contact.error.email_invalid'),
                ],
            ])
            ->add('subject', EnumType::class, [
                'class' => ContactSubject::class,
                'choice_label' => static fn (ContactSubject $subject): string => $subject->label(),
            ])
            ->add('message', TextareaType::class, [
                'constraints' => [
                    new NotBlank(message: 'contact.error.message_required'),
                    new Length(min: 10, max: 5000, minMessage: 'contact.error.message_too_short'),
                ],
            ])
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'tabindex' => '-1',
                    'autocomplete' => 'off',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
        ]);
    }
}
