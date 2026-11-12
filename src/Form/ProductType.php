<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Product Name',
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter a product name.'
                    ),
                    new Length(
                        max: 150,
                        maxMessage: 'Product name cannot exceed {{ limit }} characters.'
                    ),
                ],
                'attr' => [
                    'placeholder' => 'Enter product name',
                    'maxlength' => 150,
                    'class' => 'form-control',
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Describe your product',
                    'rows' => 4,
                    'class' => 'form-control',
                ],
            ])

            ->add('category', TextType::class, [
                'label' => 'Category',
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter a product category.'
                    ),
                    new Length(
                        max: 100,
                        maxMessage: 'Category cannot exceed {{ limit }} characters.'
                    ),
                ],
                'attr' => [
                    'placeholder' => 'e.g. Skincare, Makeup, Lip Products',
                    'maxlength' => 100,
                    'class' => 'form-control',
                ],
            ])

            ->add('price', MoneyType::class, [
                'label' => 'Price',
                'currency' => 'PHP',
                'scale' => 2,
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter a product price.'
                    ),
                    new GreaterThanOrEqual(
                        value: 0,
                        message: 'Price cannot be negative.'
                    ),
                ],
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'placeholder' => '0.00',
                    'class' => 'form-control',
                ],
            ])

            ->add('stock', IntegerType::class, [
                'label' => 'Stock Quantity',
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter the stock quantity.'
                    ),
                    new GreaterThanOrEqual(
                        value: 0,
                        message: 'Stock quantity cannot be negative.'
                    ),
                ],
                'attr' => [
                    'min' => 0,
                    'step' => 1,
                    'placeholder' => 'Enter stock quantity',
                    'class' => 'form-control',
                ],
            ])

            ->add('imageFile', FileType::class, [
                'label' => 'Product Image (JPG, PNG, WebP)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        maxSizeMessage: 'Image must not exceed 5 MB.',
                        mimeTypesMessage: 'Please upload a valid JPG, PNG, or WebP image.'
                    ),
                ],
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'class' => 'form-control',
                ],
                'help' => 'Maximum file size: 5 MB. Leave empty when editing to keep the current image.',
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
