<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, ['label' => 'Full name'])
            ->add('email', EmailType::class)
            ->add('role', ChoiceType::class, [
                'choices' => ['Staff' => 'ROLE_STAFF', 'Admin' => 'ROLE_ADMIN'],
                'disabled' => $options['is_self'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Account active',
                'required' => false,
                'disabled' => $options['is_self'],
            ]);

        if ($options['password_required']) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Confirm password'],
                'invalid_message' => 'The passwords do not match.',
                'constraints' => [
                    new NotBlank(message: 'Password is required.'),
                    new Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters.'),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'password_required' => false,
            'is_self' => false,
        ]);
    }
}