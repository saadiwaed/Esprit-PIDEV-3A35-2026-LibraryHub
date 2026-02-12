<?php

namespace App\Form;

use App\Entity\DefiPersonel;
use App\Entity\JournalLecture;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JournalLectureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user_id')
          
            ->add('titre')
            ->add('livre_id')
            ->add('date_lecture')
            ->add('duree_minutes')
            ->add('lieu')
            ->add('concentration')
            ->add('page_lues')
            ->add('resume')
            ->add('reflexion')
            ->add('note_perso')
            ->add('defi', EntityType::class, [
                'class' => DefiPersonel::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => JournalLecture::class,
        ]);
    }
}
