<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoanSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', TextType::class, [
                'label' => 'Keyword',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Search keyword...',
                ],
            ])
            ->add('filterType', ChoiceType::class, [
                'label' => 'Filter Type',
                'required' => false,
                'placeholder' => 'Choose a filter',
                'choices' => [
                    'By Member Name' => 'member',
                    'By Checkout Date' => 'checkout',
                    'By Return Date' => 'return',
                ],
            ])
            ->add('memberSearch', TextType::class, [
                'label' => 'Member Name',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Member name...',
                ],
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'From',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'To',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Search',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
        ]);
    }
}
