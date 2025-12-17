<?php

namespace App\Validator;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class FileValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof File) {
            throw new UnexpectedTypeException($constraint, File::class);
        }

        if (!$value instanceof UploadedFile) {
            $validValues = ! empty($constraint->extensions)
                ? array_keys($constraint->extensions)
                : $constraint->mimeTypes;
            //throw new UnexpectedValueException($value, UploadedFile::class);
            $this->context->buildViolation($constraint->requiredMessage)
                ->setParameter('{{ allowedExtensions }}', $this->formatValues($validValues))
                ->addViolation();
            return;
        }

        $clientMimeType = $value->getClientMimeType();
        $originalName = $value->getClientOriginalName();
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileSizeMB = $value->getSize() / 1024 / 1024; // In megabytes

        // Validate file size
        if ($constraint->maxSize !== null && $fileSizeMB > $constraint->maxSize) {
            $this->context->buildViolation($constraint->maxSizeMessage)
                ->setParameter('{{ maxSize }}', $constraint->maxSize)
                ->addViolation();
        }

        // MIME Type validation
        if (!empty($constraint->mimeTypes) && !in_array($clientMimeType, $constraint->mimeTypes, true)) {
            $this->context->buildViolation($constraint->mimeTypesMessage)
                ->setParameter('{{ type }}', $this->formatValue($clientMimeType))
                ->setParameter('{{ allowedMimeTypes }}', $this->formatValues($constraint->mimeTypes))
                ->addViolation();
        }

        // Extension validation
        if (!empty($constraint->extensions)) {
            $allowedExtensions = [];
            $validMimeTypesForExtension = [];

            // Extract MIME types associated to extensions
            foreach ($constraint->extensions as $key => $value) {
                if (is_array($value)) {
                    $allowedExtensions[] = $key;
                    $validMimeTypesForExtension[$key] = $value;
                } else {
                    $allowedExtensions[] = $value;
                }
            }

            // Verify extension
            if (!in_array($extension, $allowedExtensions, true)) {
                $this->context->buildViolation($constraint->extensionsMessage)
                    ->setParameter('{{ extension }}', $this->formatValue($extension))
                    ->setParameter('{{ allowedExtensions }}', $this->formatValues($allowedExtensions))
                    ->addViolation();
            }

            // Verify if the extension has a MIME type associated
            if (isset($validMimeTypesForExtension[$extension]) && !in_array($clientMimeType, $validMimeTypesForExtension[$extension], true)) {
                $this->context->buildViolation($constraint->mimeTypesMessage)
                    ->setParameter('{{ type }}', $this->formatValue($clientMimeType))
                    ->setParameter('{{ allowedMimeTypes }}', $this->formatValues($validMimeTypesForExtension[$extension]))
                    ->addViolation();
            }
        }
    }
}
