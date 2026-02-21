<?php

namespace App\Form;

use App\Entity\DefiPersonel;
use App\Entity\JournalLecture;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class JournalLectureType extends AbstractType
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $this->tokenStorage->getToken()?->getUser();

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
                'choice_label' => 'titre',
                'placeholder' => '-- Aucun défi --',
                'required' => false,
                'query_builder' => function (EntityRepository $er) use ($user) {
                    return $er->createQueryBuilder('d')
                        ->where('d.user_id = :userId')
                        ->andWhere('d.statut != :abandonne')
                        ->setParameter('userId', $user?->getId() ?? 0)
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