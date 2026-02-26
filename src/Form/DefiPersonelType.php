<?php

namespace App\Form;

use App\Entity\DefiPersonel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DefiPersonelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
           
            ->add('progression')
            ->add('titre')
            ->add('description')
            ->add('type_defi')
            ->add('date_debut')
            ->add('date_fin')
            ->add('objectif')
            ->add('unite')
            ->add('difficulte')
            ->add('recompense')
            ->add('statut')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DefiPersonel::class,
        ]);
    }
}
