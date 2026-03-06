<?php

namespace App\Form;

use App\Entity\Loan;
use App\Entity\Penalty;
use App\Enum\PaymentStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Penalty>
 */
class PenaltyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00',
                    'step' => '0.01',
                    'min' => '0',
                ],
            ])
            ->add('dailyRate', NumberType::class, [
                'label' => 'Taux journalier (TND)',
                'scale' => 2,
                'required' => true,
                'empty_data' => '0.50',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.50',
                    'step' => '0.01',
                    'min' => '0.01',
                ],
                'help' => 'Utilise pour calculer la penalite de retard accumulee.',
            ])
            ->add('issueDate', DateType::class, [
                'label' => 'Date d\'emission',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'date',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => '4',
                    'placeholder' => 'Informations complementaires (optionnel)',
                ],
            ])
            ->add('waived', CheckboxType::class, [
                'label' => 'Exoneree',
                'required' => false,
            ])
            ->add('status', EnumType::class, [
                'class' => PaymentStatus::class,
                'label' => 'Statut de paiement',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('loan', EntityType::class, [
                'class' => Loan::class,
                'choice_label' => static fn (Loan $loan) => (string) $loan,
                'placeholder' => 'Selectionnez un emprunt',
                'label' => 'Emprunt',
                'attr' => [
                    'class' => 'form-control',
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $penalty = $event->getData();
            $form = $event->getForm();

            $reason = $penalty instanceof Penalty ? trim($penalty->getReason()) : '';
            $reasonChoice = in_array($reason, Penalty::FIXED_REASONS, true) ? $reason : Penalty::REASON_OTHER;
            $customReason = $reasonChoice === Penalty::REASON_OTHER ? $reason : '';

            $this->addReasonFields($form, $reasonChoice, $customReason);
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $penalty = $event->getData();
            $form = $event->getForm();

            if (!$penalty instanceof Penalty) {
                return;
            }

            $reasonChoice = (string) $form->get('reason')->getData();
            $customReason = trim((string) $form->get('customReason')->getData());

            if ($reasonChoice === Penalty::REASON_OTHER) {
                if ($customReason === '') {
                    $form->get('customReason')->addError(new FormError('Veuillez preciser le motif lorsque vous choisissez "Autre".'));

                    return;
                }

                $penalty->setReason($customReason);

                return;
            }

            if (!in_array($reasonChoice, Penalty::FIXED_REASONS, true)) {
                $form->get('reason')->addError(new FormError('Veuillez selectionner un motif valide.'));

                return;
            }

            $penalty->setReason($reasonChoice);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Penalty::class,
        ]);
    }

    /**
     * @param FormInterface<Penalty> $form
     */
    private function addReasonFields(FormInterface $form, string $reasonChoice, string $customReason): void
    {
        $isOther = $reasonChoice === Penalty::REASON_OTHER;

        $form->add('reason', ChoiceType::class, [
            'label' => 'Motif',
            'mapped' => false,
            'required' => true,
            'placeholder' => 'Selectionnez un motif...',
            'choices' => [
                'Retour tardif' => Penalty::REASON_LATE_RETURN,
                'Livre endommage' => Penalty::REASON_DAMAGED_BOOK,
                'Autre motif' => Penalty::REASON_OTHER,
            ],
            'data' => $reasonChoice,
            'attr' => [
                'class' => 'form-select',
                'data-reason-select' => '1',
            ],
        ]);

        $form->add('customReason', TextType::class, [
            'label' => 'Precisez le motif',
            'mapped' => false,
            'required' => $isOther,
            'data' => $customReason,
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Ex: pages dechirees, couverture abimee...',
                'data-custom-reason' => '1',
            ],
            'help' => 'Requis uniquement si vous choisissez "Autre motif".',
        ]);
    }
}



