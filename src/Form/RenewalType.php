<?php

namespace App\Form;

use App\Entity\Renewal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Renewal>
 */
class RenewalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('previousDueDate', DateType::class, [
                'label' => 'Date limite actuelle',
                'widget' => 'single_text',
                'disabled' => true,
                'input' => 'datetime',
                'format' => 'yyyy-MM-dd',
                'help' => 'Le numero de renouvellement sera genere automatiquement.',
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                ],
            ])
            ->add('newDueDate', DateType::class, [
                'label' => 'Nouvelle date limite',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                'format' => 'yyyy-MM-dd',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'La nouvelle date limite est obligatoire.',
                    ]),
                ],
                'help' => 'Choisissez une date posterieure a la date limite actuelle.',
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'date',
                    'min' => (new \DateTime('+1 day'))->format('Y-m-d'),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Renouveler l\'emprunt',
                'attr' => ['class' => 'btn btn-success'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Renewal::class,
        ]);
    }
}



