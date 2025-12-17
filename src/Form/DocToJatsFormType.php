<?php

namespace App\Form;

use App\Service\Automark\Dom\CitationStyle\CitationStyleFactory;
use App\Service\DocConversion\App\JatsConverterOptions;
use App\Validator as CustomAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocToJatsFormType extends AbstractType
{
    private JatsConverterOptions $jatsConverterOptions;
    private CitationStyleFactory $citationStyleFactory;
    private int $maxSize;

    public function __construct(JatsConverterOptions $jatsConverterOptions, CitationStyleFactory $citationStyleFactory, int $maxUploadFileSize = 100)
    {
        $this->jatsConverterOptions = $jatsConverterOptions;
        $this->citationStyleFactory = $citationStyleFactory;
        $this->maxSize = $maxUploadFileSize;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Main file (inputFile)
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
                    ]),
                ],
            ])
            // Checkbox: Normalize/optimize document before automark
            ->add(JatsConverterOptions::NORMALIZE, CheckboxType::class, [
                'label' => 'Normalize document',
                'help' => $this->jatsConverterOptions->getDescription(JatsConverterOptions::NORMALIZE),
                'required' => false,
            ])
            ->add(JatsConverterOptions::REMOVE_SECTIONMS, TextType::class, [
                'label' => 'Remove sections (optional)',
                'help' => $this->jatsConverterOptions->getDescription(JatsConverterOptions::REMOVE_SECTIONMS),
                'required' => false,
                'attr' => [
                    'placeholder' => 'Sections to remove (separated by space)',
                ],
            ])
            // Optional Front metadata xml file
            ->add(JatsConverterOptions::FRONT_XML_FILE, FileType::class, [
                'label' => 'Front file (optional)',
                'help' => $this->jatsConverterOptions->getDescription(JatsConverterOptions::FRONT_XML_FILE),
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => $this->maxSize.'M',
                        'mimeTypes' => [
                            'text/xml',
                            'text/plain',
                        ],
                        'mimeTypesMessage' => $this->jatsConverterOptions->getInvalidArgumentMessage(JatsConverterOptions::FRONT_XML_FILE),
                    ]),
                ],
            ])
            // Optional Citation Style File
            ->add(JatsConverterOptions::BIBLIOGRAPHY_FILE, FileType::class, [
                'label' => 'Bibliography File (optional)',
                'help' => $this->jatsConverterOptions->getDescription(JatsConverterOptions::BIBLIOGRAPHY_FILE),
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => $this->maxSize.'M',
                        'mimeTypes' => [
                            'application/json',
                            'text/plain',
                        ],
                        'mimeTypesMessage' => $this->jatsConverterOptions->getInvalidArgumentMessage(JatsConverterOptions::BIBLIOGRAPHY_FILE),
                    ]),
                ],
                /*'constraints' => [
                    new CustomAssert\File([
                        'maxSize' => $this->maxSize,
                        'extensions' => [
                            'json' => ['application/json','text/plain'],
                            'txt' => ['text/plain'],
                        ],
                        'mimeTypesMessage' => 'Only JSON or TXT are allowed.',
                    ]),
                ],*/
            ])
            // Select: supported styles
            ->add(JatsConverterOptions::CITATION_STYLE, ChoiceType::class, [
                'label' => 'Automark citation style',
                'help' => $this->jatsConverterOptions->getDescription(JatsConverterOptions::CITATION_STYLE),
                'choices' => $this->citationStyleFactory->getNames(true),
                'required' => false,
            ])
            // Checkboxes
            ->add(JatsConverterOptions::SET_BIBLIOGRAPHY_MIXED_CITATIONS, CheckboxType::class, [
                'label' => 'Scielo compatibility',
                'help' => $this->jatsConverterOptions->getDescription(JatsConverterOptions::SET_BIBLIOGRAPHY_MIXED_CITATIONS),
                'required' => false,
            ])
            ->add(JatsConverterOptions::SET_FIGURE_TITLES, CheckboxType::class, [
                'label' => 'Set figure titles',
                'help' => $this->jatsConverterOptions->getDescription(JatsConverterOptions::SET_FIGURE_TITLES),
                'required' => false,
            ])
            ->add(JatsConverterOptions::SET_TABLE_TITLES, CheckboxType::class, [
                'label' => 'Set table titles',
                'help' => $this->jatsConverterOptions->getDescription(JatsConverterOptions::SET_TABLE_TITLES),
                'required' => false,
            ])
            ->add(JatsConverterOptions::REPLACE_TITLES_WITH_REFERENCES, CheckboxType::class, [
                'label' => 'Replace figure or table titles with a reference',
                'help' => $this->jatsConverterOptions->getDescription(JatsConverterOptions::REPLACE_TITLES_WITH_REFERENCES),
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }
}
