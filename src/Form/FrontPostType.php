<?php

namespace App\Form;

use App\Entity\Post;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Yippy\ToastUiEditorBundle\Form\Type\ToastUiEditorType;

/**
 * @extends AbstractType<Post>
 */
class FrontPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'placeholder' => 'Donnez un titre a votre post',
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
                    'placeholder' => 'Partagez votre message avec la communaute',
                    'class' => 'form-control forum-markdown-source',
                    'rows' => 10,
                ],
            ])
            ->add('externalUrl', UrlType::class, [
                'label' => 'Lien externe (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://example.com',
                    'class' => 'form-control',
                ],
            ])
            ->add('attachmentFiles', FileType::class, [
                'label' => 'Pieces jointes',
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
                            'mimeTypesMessage' => 'Format non supporte. Formats acceptes : JPG, PNG, GIF, PDF, DOC, DOCX',
                            'maxSizeMessage' => 'Le fichier est trop volumineux. Taille maximum : {{ limit }} {{ suffix }}',
                        ]),
                    ]),
                ],
            ])
            ->add('spoilerWarning', CheckboxType::class, [
                'label' => 'Ce contenu contient un spoiler',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('allowComments', CheckboxType::class, [
                'label' => 'Autoriser les commentaires',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Publier le post',
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


