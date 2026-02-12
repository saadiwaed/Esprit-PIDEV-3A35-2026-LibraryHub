<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\ClubStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ClubType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('description')
            ->add('category')
            ->add('meetingDate')
            ->add('meetingLocation')
            ->add('capacity')
            ->add('isPrivate', CheckboxType::class, [
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => ClubStatus::cases(),
                'choice_value' => 'value',
                'choice_label' => function (ClubStatus $status) {
                    return $status->getLabel();
                },
            ])
            ->add('createdDate', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('imageFile', VichImageType::class, [
                'label' => 'Image du club',
                'required' => false, // Changed to false for edit
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('founder', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
            ->add('members', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
                'multiple' => true,
            ])
            ->add('createdBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
            ->add('organizedEvents', EntityType::class, [
                'class' => Event::class,
                'choice_label' => 'id',
                'multiple' => true,
            ]);
            // REMOVED the ->add('save', SubmitType::class) line
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
        ]);
    }
}