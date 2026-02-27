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
                'label' => 'Nom de la communaute',
                'attr' => [
                    'placeholder' => 'Ex: Club de Lecture Classique',
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Decrivez votre communaute...',
                    'class' => 'form-control',
                    'rows' => 4,
                ],
            ])
            ->add('purpose', TextType::class, [
                'label' => 'Objectif',
                'attr' => [
                    'placeholder' => 'Ex: Partager notre passion pour la litterature',
                    'class' => 'form-control',
                ],
            ])
            ->add('rules', TextareaType::class, [
                'label' => 'Regles',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Regles de la communaute (optionnel)',
                    'class' => 'form-control',
                    'rows' => 3,
                ],
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icone',
                'required' => false,
                'help' => 'Saisissez un genre (ex: Science-fiction, Romance, Histoire).',
                'attr' => [
                    'placeholder' => 'Ex: Science-fiction',
                    'class' => 'form-control',
                    'list' => 'community_icon_suggestions',
                    'autocomplete' => 'off',
                    'spellcheck' => 'false',
                ],
            ])
            ->add('welcomeMessage', TextareaType::class, [
                'label' => 'Message de bienvenue',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Message affiche aux nouveaux membres',
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
                'label' => 'Communaute publique',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary'],
            ])
        ;

        if ($options['allow_status_change']) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => CommunityStatus::cases(),
                'choice_value' => 'value',
                'choice_label' => function (CommunityStatus $status) {
                    return $status->getLabel();
                },
                'attr' => ['class' => 'form-select'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Community::class,
            'allow_status_change' => false,
        ]);

        $resolver->setAllowedTypes('allow_status_change', 'bool');
    }
}
