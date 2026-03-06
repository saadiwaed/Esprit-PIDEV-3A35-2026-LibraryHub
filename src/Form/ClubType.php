<?php

namespace App\Form;

use App\Entity\Club;
use App\Enum\ClubStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * @extends AbstractType<Club>
 */
class ClubType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Nom du club',
                'attr' => [
                    'placeholder' => 'Ex: Club de lecture Fantasy',
                    'class' => 'form-control'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Décrivez votre club, ses objectifs...',
                    'class' => 'form-control'
                ]
            ])

            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Roman' => 'Roman',
                    'Science-Fiction' => 'Science-Fiction',
                    'Fantasy' => 'Fantasy',
                    'Policier' => 'Policier',
                    'Histoire' => 'Histoire',
                    'Biographie' => 'Biographie',
                    'Poésie' => 'Poésie',
                    'Théâtre' => 'Théâtre',
                    'Philosophie' => 'Philosophie',
                    'Sciences' => 'Sciences',
                    'Arts' => 'Arts',
                    'Développement personnel' => 'Développement personnel',
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('meetingDate', DateTimeType::class, [
                'label' => 'Date de la prochaine réunion',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('meetingLocation', TextType::class, [
                'label' => 'Lieu de réunion',
                'attr' => [
                    'placeholder' => 'Ex: Bibliothèque, Zoom, Discord...',
                    'class' => 'form-control'
                ]
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Capacité maximum',
                'attr' => [
                    'min' => 2,
                    'max' => 100,
                    'placeholder' => '20',
                    'class' => 'form-control'
                ]
            ])
            ->add('isPrivate', CheckboxType::class, [
                'label' => 'Club privé',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('imageFile', VichImageType::class, [
                'label' => 'Image du club',
                'required' => false,
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri' => true,
                'attr' => ['class' => 'form-control']
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

