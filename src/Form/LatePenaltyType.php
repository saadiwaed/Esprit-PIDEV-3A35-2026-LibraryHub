<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LatePenaltyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $defaultDailyRate = (float) ($options['default_daily_rate'] ?? 0.50);

        $builder
            ->add('amount', NumberType::class, [
                'label' => 'Montant de la pénalité (TND)',
                'scale' => 2,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant est obligatoire.'),
                    new Assert\Positive(message: 'Le montant doit etre strictement positif.'),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'min' => '0.01',
                    'step' => '0.01',
                    'placeholder' => 'Ex: 2.50',
                    'readonly' => true,
                ],
                'help' => 'Calculé automatiquement (jours de retard × taux journalier).',
            ])
            ->add('dailyRate', NumberType::class, [
                'label' => 'Taux journalier (TND)',
                'scale' => 2,
                'required' => true,
                'data' => $defaultDailyRate,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le taux journalier est obligatoire.'),
                    new Assert\Positive(message: 'Le taux journalier doit etre strictement positif.'),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'min' => '0.01',
                    'step' => '0.01',
                    'placeholder' => 'Ex: 0.50',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes supplémentaires',
                'required' => false,
                'constraints' => [
                    new Assert\Length(
                        max: 1000,
                        maxMessage: 'Les notes ne doivent pas depasser {{ limit }} caracteres.'
                    ),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Informations complementaires...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'late_penalty_form',
            'default_daily_rate' => 0.50,
        ]);
    }
}
