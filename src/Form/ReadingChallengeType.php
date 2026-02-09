<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\ReadingChallenge;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReadingChallengeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('goal')
            ->add('type')
            ->add('status')
            ->add('reward')
            ->add('rules')
            ->add('difficulty')
            ->add('startDate')
            ->add('endDate')
            ->add('createdDate')
            ->add('club', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'id',
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
            'data_class' => ReadingChallenge::class,
        ]);
    }
}
