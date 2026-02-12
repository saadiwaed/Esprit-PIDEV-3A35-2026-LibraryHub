<?php

namespace App\Form;

use App\Entity\Community;
use App\Enum\CommunityStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommunityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la communauté',
                'attr' => [
                    'placeholder' => 'Ex: Club de Lecture Classique',
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Décrivez votre communauté...',
                    'class' => 'form-control',
                    'rows' => 4,
                ],
            ])
            ->add('purpose', TextType::class, [
                'label' => 'Objectif',
                'attr' => [
                    'placeholder' => 'Ex: Partager notre passion pour la littérature',
                    'class' => 'form-control',
                ],
            ])
            ->add('rules', TextareaType::class, [
                'label' => 'Règles',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Règles de la communauté (optionnel)',
                    'class' => 'form-control',
                    'rows' => 3,
                ],
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icône (classe CSS)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: fas fa-book',
                    'class' => 'form-control',
                ],
            ])
            ->add('welcomeMessage', TextareaType::class, [
                'label' => 'Message de bienvenue',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Message affiché aux nouveaux membres',
                    'class' => 'form-control',
                    'rows' => 2,
                ],
            ])
            ->add('contactEmail', EmailType::class, [
                'label' => 'Email de contact',
                'required' => false,
                'attr' => [
                    'placeholder' => 'contact@example.com',
                    'class' => 'form-control',
                ],
            ])
            ->add('isPublic', CheckboxType::class, [
                'label' => 'Communauté publique',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => CommunityStatus::cases(),
                'choice_value' => 'value',
                'choice_label' => function (CommunityStatus $status) {
                    return $status->getLabel();
                },
                'attr' => ['class' => 'form-select'],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Community::class,
        ]);
    }
}