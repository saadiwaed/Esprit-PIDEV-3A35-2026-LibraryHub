<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Event;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, ['required' => false])
            ->add('description', null, ['required' => false])
            ->add('startDateTime', null, ['required' => false])
            ->add('endDateTime', null, ['required' => false])
            ->add('location', null, ['required' => false])
            ->add('capacity', null, ['required' => false])
            ->add('registrationDeadline', null, ['required' => false])
            ->add('status', null, ['required' => false])
            ->add('createdDate', null, ['required' => false])
            ->add('image', null, ['required' => false])
            ->add('createdBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
                'required' => false,
            ])
            ->add('organizingClubs', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'id',
                'multiple' => true,
                'required' => false,
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
