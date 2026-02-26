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
                'label' => 'Mot-clé',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Rechercher…',
                ],
            ])
            ->add('filterType', ChoiceType::class, [
                'label' => 'Type de filtre',
                'required' => false,
                'placeholder' => 'Choisir un filtre',
                'choices' => [
                    'Par membre' => 'member',
                    'Par date d\'emprunt' => 'checkout',
                    'Par date de retour' => 'return',
                ],
            ])
            ->add('memberSearch', TextType::class, [
                'label' => 'Membre',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom, prénom, email…',
                ],
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Rechercher',
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
