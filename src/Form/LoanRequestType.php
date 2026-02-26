<?php

namespace App\Form;

use App\Entity\LoanRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class LoanRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bookId', IntegerType::class, [
                'label' => 'ID du livre',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Ex: 1',
                ],
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Numéro de téléphone (+216 obligatoire)',
                'required' => true,
                'invalid_message' => 'Le numéro doit contenir exactement 8 chiffres après +216',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+216 XX XXX XXX',
                    'autocomplete' => 'tel',
                    'inputmode' => 'numeric',
                    'data-tn-phone' => '1',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le numéro de téléphone est obligatoire.'),
                    new Regex(
                        pattern: '/^\+216\d{8}$/',
                        message: 'Le numéro doit contenir exactement 8 chiffres après +216'
                    ),
                ],
            ])
            ->add('desiredLoanDate', DateType::class, [
                'label' => 'Date d\'emprunt souhaitée',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('desiredReturnDate', DateType::class, [
                'label' => 'Date de retour souhaitée',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Informations complémentaires...',
                ],
            ]);

        $builder->get('phoneNumber')->addModelTransformer(new CallbackTransformer(
            static fn(?string $modelValue): string => $modelValue ?? '',
            static function (mixed $submittedValue): ?string {
                $value = preg_replace('/\\s+/', '', trim((string) ($submittedValue ?? '')));
                $value = str_replace(['-', '(', ')', '.'], '', $value);

                if ($value === '') {
                    return null;
                }

                if (str_starts_with($value, '+216')) {
                    $digits = substr($value, 4);
                } elseif (str_starts_with($value, '216')) {
                    $digits = substr($value, 3);
                } else {
                    $digits = $value;
                }

                if (preg_match('/^\\d{8}$/', $digits) !== 1) {
                    throw new TransformationFailedException('Le numéro doit contenir exactement 8 chiffres après +216');
                }

                return '+216' . $digits;
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LoanRequest::class,
        ]);
    }
}
