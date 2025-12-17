<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/// /**
///  * @Annotation
///  * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
///  */
/// #[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class File extends Constraint
{
    /**
     * @var int|null
     * Tamaño máximo en **megabytes (MB)**
     */
    public ?int $maxSize = null;
    public string $maxSizeMessage = 'The file is too large. Allowed maximum size is {{ maxSize }}MB.';

    /**
     * @var array|string[]
     * Lista de extensiones permitidas
     * Puede ser:
     * - Una lista simple de extensiones `['pdf', 'docx']`
     * - Un array asociativo `['docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']]`
     */
    public array $extensions = [];
    public string $extensionsMessage = 'The extension {{ extension }} is not valid. Allowed extensions: {{ allowedExtensions }}.';

    /**
     * @var string[]
     * Lista de MIME Types permitidos
     */
    public array $mimeTypes = [];
    public string $mimeTypesMessage = 'The mime type {{ type }} is not valid. Allowed mime types: {{ allowedMimeTypes }}.';

    public string $requiredMessage = "You must upload a valid {{ allowedExtensions }} file.";
}
