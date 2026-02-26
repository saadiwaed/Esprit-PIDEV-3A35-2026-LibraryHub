<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\ClubStatus; // ADD THIS
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType; // ADD THIS
use Symfony\Component\Form\Extension\Core\Type\DateType; // ADD for dates
use Symfony\Component\Form\Extension\Core\Type\CheckboxType; // ADD for boolean
use Symfony\Component\Form\Extension\Core\Type\SubmitType; 
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClubType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, ['required' => false])
            ->add('description', null, ['required' => false])
            ->add('category', null, ['required' => false])
            ->add('meetingDate', null, ['required' => false])
            ->add('meetingLocation', null, ['required' => false])
            ->add('capacity', null, ['required' => false])
            ->add('isPrivate', CheckboxType::class, [
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'required' => false,
                'choices' => ClubStatus::cases(),
                'choice_value' => 'value',
                'choice_label' => function (ClubStatus $status) {
                    return $status->getLabel();
                },
            ])
            ->add('createdDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('image', null, ['required' => false])
            ->add('founder', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
                'required' => false,
            ])
            ->add('members', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
                'multiple' => true,
                'required' => false,
            ])
            ->add('createdBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
                'required' => false,
            ])
            ->add('organizedEvents', EntityType::class, [
                'class' => Event::class,
                'choice_label' => 'id',
                'multiple' => true,
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
        ]);
    }
}