<?php

namespace App\Form;

use App\Validator as CustomAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadDocFileFormType extends AbstractType
{
    const NAME = 'upload_doc_file_form';

    private int $maxSize;

    public function __construct(int $maxUploadFileSize = 100)
    {
        $this->maxSize = $maxUploadFileSize;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('inputFile', FileType::class, [
                'label' => 'Input File',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new CustomAssert\File([
                        'maxSize' => $this->maxSize,
                        'extensions' => [
                            'odt' => [ 'application/vnd.oasis.opendocument.text' ],
                            'doc' => [ 'application/msword' ],
                            'docx' => [ 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ],
                        ],
                        //'requiredMessage' => 'Por favor, sube un archivo válido: .doc, .docx u .odt',
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
