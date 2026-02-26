<?php

namespace App\Form;

use App\Entity\BookCopy;
use App\Entity\Loan;
use App\Entity\User;
use App\Enum\LoanStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $loan = $options['data'] instanceof Loan ? $options['data'] : null;
        $isAlreadyReturned = $loan?->getReturnDate() instanceof \DateTimeInterface;
        $allowReturnDateEdit = (bool) $options['allow_return_date_edit'];

        $statusChoices = $isAlreadyReturned
            ? [LoanStatus::RETURNED]
            : [LoanStatus::ACTIVE, LoanStatus::OVERDUE];

        $builder
            ->add('checkoutTime', DateTimeType::class, [
                'label' => 'Date de sortie',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Selectionnez la date de sortie',
                ]
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Date limite',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
                'help' => 'La date de retour doit être après ou égale à la date d\'emprunt',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Selectionnez la date limite',
                ]
            ])
            ->add('returnDate', DateTimeType::class, [
                'label' => 'Date de retour (optionnelle - laisser vide)',
                'widget' => 'single_text',
                'html5' => true,
                'required' => false,
                'empty_data' => null,
                'mapped' => $allowReturnDateEdit,
                'disabled' => !$allowReturnDateEdit || $isAlreadyReturned,
                'data' => $isAlreadyReturned ? $loan?->getReturnDate() : null,
                'help' => $allowReturnDateEdit
                    ? 'Laissez vide tant que le livre n\'est pas retourne.'
                    : 'Renseignee uniquement via le bouton "Marquer comme retourne".',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Laisser vide si non retourne',
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_reduce(
                    $statusChoices,
                    fn($carry, $status) => $carry + [$status->name => $status],
                    []
                ),
                'required' => true,
                'disabled' => $isAlreadyReturned,
                'help' => $isAlreadyReturned
                    ? 'Le statut est verrouille apres retour.'
                    : 'Le statut RETURNED est defini automatiquement via l\'action de retour.',
                'attr' => [
                    'class' => 'form-control',
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => '4',
                    'placeholder' => 'Ajoutez des notes optionnelles',
                ]
            ])
            ->add('bookCopy', EntityType::class, [
                'label' => 'Exemplaire',
                'class' => BookCopy::class,
                'choice_label' => fn(BookCopy $copy) => sprintf('Book Copy #%d', $copy->getId()),
                'placeholder' => 'Selectionnez un exemplaire',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ]
            ])
            ->add('member', EntityType::class, [
                'label' => 'Adherent',
                'class' => User::class,
                'choice_label' => fn(User $user) => $user->getFirstName() . ' ' . $user->getLastName() . ' (' . $user->getEmail() . ')',
                'placeholder' => 'Selectionnez un adherent',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Loan::class,
            'allow_return_date_edit' => false,
        ]);

        $resolver->setAllowedTypes('allow_return_date_edit', 'bool');
    }
}
