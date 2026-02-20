<?php

namespace App\Form;

use App\Entity\DefiPersonel;
use App\Entity\JournalLecture;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository; // ✅ IMPORT À AJOUTER

class JournalLectureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
                'choice_label' => 'titre', // ✅ CHANGE 'id' EN 'titre' POUR AVOIR LE NOM DU DÉFI
                'placeholder' => '-- Aucun défi --',
                'required' => false,
                // ✅ AJOUT DU FILTRE PAR UTILISATEUR
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('d')
                        ->where('d.user_id = :userId')
                        ->andWhere('d.statut != :abandonne')
                        ->setParameter('userId', 1) // 🔥 CHANGE ICI POUR TESTS (1,2,3...)
                        ->setParameter('abandonne', 'Abandonné')
                        ->orderBy('d.date_fin', 'ASC');
                },
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