<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Event;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Enum\EventStatus;
use App\Enum\EventTypes;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType; 
use Vich\UploaderBundle\Form\Type\VichImageType;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('description')
            ->add('startDateTime')
            ->add('endDateTime')
            ->add('location')
            ->add('capacity')
            ->add('registrationDeadline')
            ->add('status', ChoiceType::class, [
                'choices' => EventStatus::cases(),
                'choice_value' => 'value',
                'choice_label' => function (EventStatus $status) {
                    return $status->getLabel();
                },
            ])
            ->add('type', ChoiceType::class, [
                'choices' => EventTypes::cases(),
                'choice_value' => 'value',
                'choice_label' => function (EventTypes $type) {
                    return $type->getLabel();
        },
        'choice_attr' => function (EventTypes $type) {
            return ['data-icon' => $type->getIcon()];
        },
        'label' => 'Type d\'événement',
        'attr' => ['class' => 'form-select'],
    ])
            ->add('createdDate')
            ->add('imageFile', VichImageType::class, [
                'label' => 'Image de l\'événement',
                'required' => false,
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri' => true,
                'attr' => ['class' => 'form-control']
])
            ->add('createdBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
            
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
