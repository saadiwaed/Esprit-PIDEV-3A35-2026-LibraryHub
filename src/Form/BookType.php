<?php

namespace App\Form;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class BookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => '-- Choisir une catégorie --',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('author', EntityType::class, [
                'class' => Author::class,
                'choice_label' => function (Author $author) {
                    return $author->getFullName();
                },
                'label' => 'Auteur',
                'required' => false,
                'placeholder' => '-- Choisir un auteur --',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('publisher', TextType::class, [
                'label' => 'Éditeur',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('publicationYear', IntegerType::class, [
                'label' => 'Année de publication',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('pageCount', IntegerType::class, [
                'label' => 'Nombre de pages',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('language', TextType::class, [
                'label' => 'Langue',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Français'],
            ])
            ->add('coverImageFile', FileType::class, [
                'label' => 'Image de couverture',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez télécharger une image (JPEG, PNG, GIF ou WebP).',
                    ]),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'choices' => [
                    'Disponible' => 'available',
                    'Emprunté' => 'borrowed',
                    'En maintenance' => 'maintenance',
                    'Réservé' => 'reserved',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('createdAt', DateTimeType::class, [
                'label' => 'Date d\'ajout',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
        ]);
    }
}
