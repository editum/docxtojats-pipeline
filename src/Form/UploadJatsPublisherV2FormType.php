<?php

namespace App\Form;

use App\Validator as CustomAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadJatsPublisherV2FormType extends AbstractType
{
    const NAME = 'upload_jats_publisher_v2_form';
    private int $maxSize;

    public function __construct(int $maxUploadFileSize = 100)
    {
        $this->maxSize = $maxUploadFileSize;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('inputFile', FileType::class, [
                'label' => 'Input ZIP File',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new CustomAssert\File([
                        'maxSize' => $this->maxSize,
                        'extensions' => [
                            'zip' => [ 'application/zip', 'application/x-zip', 'application/x-zip-compressed']
                        ],
                    ]),
                ],
            ])
            ->add('theme', TextType::class, [
                'label' => 'Theme',
                'mapped' => false,
                'required' => false,
                'empty_data' => 'base',
            ])
            ->add('format', ChoiceType::class, [
                'label' => 'Format',
                'mapped' => false,
                'required' => false,
                'choices' => [
                    'All' => 'all',
                    'HTML only' => 'html',
                    'PDF only' => 'pdf',
                ],
                'empty_data' => 'all',
            ])
            ->add('preview', CheckboxType::class, [
                'label' => 'Generate Vivliostyle preview wrapper',
                'mapped' => false,
                'required' => false,
            ])
            ->add('configFile', FileType::class, [
                'label' => 'Config JSON File',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new CustomAssert\File([
                        'maxSize' => 5, // 5MB should be plenty for json
                        'extensions' => [
                            'json' => [ 'application/json', 'text/plain' ]
                        ],
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return static::NAME;
    }
}
