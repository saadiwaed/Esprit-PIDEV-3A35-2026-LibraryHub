<?php

namespace App\Form;

use App\Entity\ReadingProfile;
use App\Entity\User;
use App\Repository\UserRepository;
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
        $isEdit = $options['is_edit'];
        $currentUserId = $options['current_user_id'];

        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'label' => 'Select User',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Choose a user...',
                'query_builder' => function (UserRepository $repository) use ($isEdit, $currentUserId) {
                    $qb = $repository->createQueryBuilder('u')
                        ->leftJoin('u.readingProfile', 'rp')
                        ->where('rp.id IS NULL');
                    
                    // If editing, include the current user
                    if ($isEdit && $currentUserId) {
                        $qb->orWhere('u.id = :userId')
                           ->setParameter('userId', $currentUserId);
                    }
                    
                    return $qb->orderBy('u.firstName', 'ASC');
                }
            ])
            ->add('favoriteGenres', ChoiceType::class, [
                'label' => 'Favorite Genres',
                'choices' => [
                    'Fiction' => 'Fiction',
                    'Non-Fiction' => 'Non-Fiction',
                    'Science Fiction' => 'Science Fiction',
                    'Fantasy' => 'Fantasy',
                    'Mystery' => 'Mystery',
                    'Thriller' => 'Thriller',
                    'Romance' => 'Romance',
                    'Horror' => 'Horror',
                    'Biography' => 'Biography',
                    'History' => 'History',
                    'Self-Help' => 'Self-Help',
                    'Poetry' => 'Poetry',
                    'Drama' => 'Drama',
                    'Adventure' => 'Adventure',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'attr' => ['class' => 'genre-checkboxes']
            ])
            ->add('preferredLanguages', ChoiceType::class, [
                'label' => 'Preferred Languages',
                'choices' => [
                    'English' => 'English',
                    'French' => 'French',
                    'Spanish' => 'Spanish',
                    'German' => 'German',
                    'Arabic' => 'Arabic',
                    'Chinese' => 'Chinese',
                    'Japanese' => 'Japanese',
                    'Italian' => 'Italian',
                    'Portuguese' => 'Portuguese',
                    'Russian' => 'Russian',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'attr' => ['class' => 'language-checkboxes']
            ])
            ->add('readingGoalPerMonth', IntegerType::class, [
                'label' => 'Reading Goal (Books per Month)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'e.g., 5'
                ],
                'help' => 'How many books do you aim to read each month?'
            ])
            ->add('totalBooksRead', IntegerType::class, [
                'label' => 'Total Books Read',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'placeholder' => '0'
                ],
                'help' => 'Total number of books read so far'
            ])
            ->add('averageRating', NumberType::class, [
                'label' => 'Average Rating',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 5,
                    'step' => 0.1,
                    'placeholder' => 'e.g., 4.5'
                ],
                'help' => 'Average rating given to books (0-5)'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReadingProfile::class,
            'is_edit' => false,
            'current_user_id' => null,
        ]);
    }
}
