<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class ReadingChallengeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('goal', null, ['required' => false])
            ->add('type', null, ['required' => false])
            ->add('status', null, ['required' => false])
            ->add('reward', null, ['required' => false])
            ->add('rules', null, ['required' => false])
            ->add('difficulty', null, ['required' => false])
            ->add('startDate', null, ['required' => false])
            ->add('endDate', null, ['required' => false])
            ->add('createdDate', null, ['required' => false])
            ->add('club', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'id',
                'required' => false,
            ])
            ->add('createdBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}



