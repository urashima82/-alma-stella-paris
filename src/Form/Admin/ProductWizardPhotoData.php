<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\SourcePhoto;
use App\Enum\PhotoAngle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class ProductWizardPhotoData
{
    #[Assert\File(
        maxSize: SourcePhoto::MAX_FILE_SIZE_BYTES,
        mimeTypes: SourcePhoto::ACCEPTED_MIME_TYPES,
        mimeTypesMessage: 'Format accepté : JPEG, PNG ou WebP (max 10 Mo).'
    )]
    public ?UploadedFile $file = null;

    public PhotoAngle $angle = PhotoAngle::Front;
}
