<?php

namespace App\Form;

use App\Entity\LoanRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class LoanRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bookId', IntegerType::class, [
                'mapped' => false,
                'label' => 'ID du livre',
                'attr' => [
                    'min' => 1,
                    'placeholder' => 'Ex: 123',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: "L'ID du livre est obligatoire."),
                    new Assert\Positive(message: "L'ID du livre est invalide."),
                ],
            ])
            ->add('desiredLoanDate', DateType::class, [
                'label' => "Date d'emprunt souhaitée",
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotNull(message: "La date d'emprunt souhaitée est obligatoire."),
                    new Assert\GreaterThanOrEqual(value: 'today', message: "La date d'emprunt souhaitée doit être aujourd'hui ou plus tard."),
                ],
            ])
            ->add('desiredReturnDate', DateType::class, [
                'label' => 'Date de retour souhaitée',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotNull(message: 'La date de retour souhaitée est obligatoire.'),
                ],
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'label' => 'Notes / commentaires',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Optionnel : précisez votre demande (édition, urgence, etc.)',
                ],
                'constraints' => [
                    new Assert\Length(max: 2000, maxMessage: 'Les notes ne doivent pas dépasser {{ limit }} caractères.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'data_class' => LoanRequest::class,
        ]);
    }
}

