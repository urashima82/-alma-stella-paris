<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Enum\PhotoAngle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class ProductWizardPhotoData
{
    #[Assert\File(
        maxSize: '10M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'Format accepté : JPEG, PNG ou WebP (max 10 Mo).'
    )]
    public ?UploadedFile $file = null;

    public PhotoAngle $angle = PhotoAngle::Front;
}
