<?php

namespace App\Form;

use App\Entity\Community;
use App\Entity\Post;
use App\Enum\PostStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Yippy\ToastUiEditorBundle\Form\Type\ToastUiEditorType;

/**
 * @extends AbstractType<Post>
 */
class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du post',
                'attr' => [
                    'placeholder' => 'Ex: Recommandation de lecture',
                    'class' => 'form-control',
                ],
            ])
            ->add('content', ToastUiEditorType::class, [
                'label' => 'Contenu',
                'jquery' => [
                    'enable' => false,
                ],
                'editor' => [
                    'options' => [
                        'initial_edit_type' => 'wysiwyg',
                        'preview_style' => 'tab',
                    ],
                ],
                'extensions' => [
                    'codeSyntaxHighlight' => [],
                    'tableMergedCell' => [],
                ],
                'attr' => [
                    'placeholder' => 'Redigez votre post...',
                    'class' => 'form-control',
                    'rows' => 10,
                ],
            ])
            ->add('community', EntityType::class, [
                'class' => Community::class,
                'choice_label' => 'name',
                'label' => 'Communauté',
                'placeholder' => '-- Sélectionnez une communauté --',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => PostStatus::cases(),
                'choice_value' => 'value',
                'choice_label' => function (PostStatus $status) {
                    return $status->getLabel();
                },
                'attr' => ['class' => 'form-select'],
            ])
            ->add('spoilerWarning', CheckboxType::class, [
                'label' => 'Avertissement spoiler',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('isPinned', CheckboxType::class, [
                'label' => 'Épingler ce post',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('allowComments', CheckboxType::class, [
                'label' => 'Autoriser les commentaires',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('externalUrl', UrlType::class, [
                'label' => 'Lien externe (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://example.com/article',
                    'class' => 'form-control',
                ],
            ])
            ->add('attachmentFiles', FileType::class, [
                'label' => 'Pièces jointes',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.jpg,.jpeg,.png,.gif,.pdf,.doc,.docx',
                ],
                'constraints' => [
                    new All([
                        new File([
                            'maxSize' => '5M',
                            'mimeTypes' => [
                                'image/jpeg',
                                'image/png',
                                'image/gif',
                                'image/webp',
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ],
                            'mimeTypesMessage' => 'Format non supporté. Formats acceptés : JPG, PNG, GIF, PDF, DOC, DOCX',
                            'maxSizeMessage' => 'Le fichier est trop volumineux. Taille maximum : {{ limit }} {{ suffix }}',
                        ]),
                    ]),
                ],
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
            'data_class' => Post::class,
        ]);
    }
}


