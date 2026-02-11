<?php

namespace App\Form;

use App\Entity\ReadingProfile;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReadingProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'label' => 'User',
                'placeholder' => 'Select a user',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('favoriteGenres', ChoiceType::class, [
                'label' => 'Favorite Genres',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choices' => [
                    'Fiction' => 'Fiction',
                    'Non-Fiction' => 'Non-Fiction',
                    'Mystery' => 'Mystery',
                    'Science Fiction' => 'Science Fiction',
                    'Fantasy' => 'Fantasy',
                    'Romance' => 'Romance',
                    'Thriller' => 'Thriller',
                    'Horror' => 'Horror',
                    'Biography' => 'Biography',
                    'History' => 'History',
                    'Self-Help' => 'Self-Help',
                    'Poetry' => 'Poetry',
                    'Drama' => 'Drama',
                    'Children' => 'Children',
                    'Young Adult' => 'Young Adult',
                ],
                'attr' => [
                    'class' => 'form-check-group',
                ],
            ])
            ->add('preferredLanguages', ChoiceType::class, [
                'label' => 'Preferred Languages',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choices' => [
                    'English' => 'English',
                    'French' => 'French',
                    'Arabic' => 'Arabic',
                    'Spanish' => 'Spanish',
                    'German' => 'German',
                    'Italian' => 'Italian',
                    'Portuguese' => 'Portuguese',
                    'Chinese' => 'Chinese',
                    'Japanese' => 'Japanese',
                ],
                'attr' => [
                    'class' => 'form-check-group',
                ],
            ])
            ->add('readingGoalPerMonth', IntegerType::class, [
                'label' => 'Reading Goal (books per month)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter monthly reading goal',
                    'min' => 1,
                ],
            ])
            ->add('totalBooksRead', IntegerType::class, [
                'label' => 'Total Books Read',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter total books read',
                    'min' => 0,
                ],
            ])
            ->add('averageRating', NumberType::class, [
                'label' => 'Average Rating',
                'required' => false,
                'scale' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter average rating (0-5)',
                    'min' => 0,
                    'max' => 5,
                    'step' => 0.1,
                ],
                'help' => 'Rating from 0 to 5',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReadingProfile::class,
        ]);
    }
}
