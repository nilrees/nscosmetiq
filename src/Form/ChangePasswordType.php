<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current Password',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'current-password',
                    'placeholder' => 'Enter current password',
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Current password is required.'
                    ),
                ],
            ])

            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,

                'first_options' => [
                    'label' => 'New Password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Enter new password',
                    ],
                ],

                'second_options' => [
                    'label' => 'Confirm New Password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Confirm new password',
                    ],
                ],

                'invalid_message' => 'Passwords do not match.',

                'constraints' => [
                    new Assert\NotBlank(
                        message: 'New password is required.'
                    ),
                    new Assert\Length(
                        min: 8,
                        minMessage: 'Password must be at least 8 characters.'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
