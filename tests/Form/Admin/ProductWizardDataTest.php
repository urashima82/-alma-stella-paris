<?php

declare(strict_types=1);

namespace App\Tests\Form\Admin;

use App\Enum\PhotoAngle;
use App\Form\Admin\ProductWizardData;
use App\Form\Admin\ProductWizardPhotoData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProductWizardDataTest extends TestCase
{
    public function testZeroPhotosFailsValidation(): void
    {
        $data = $this->newData(0);
        $violations = $this->validator()->validate($data);

        self::assertTrue($this->hasViolationFor($violations, 'photos'));
    }

    public function testOnePhotoFailsValidation(): void
    {
        $data = $this->newData(1);
        $violations = $this->validator()->validate($data);

        self::assertTrue($this->hasViolationFor($violations, 'photos'));
    }

    public function testTwoPhotosPassValidation(): void
    {
        $data = $this->newData(2);
        $violations = $this->validator()->validate($data);

        self::assertFalse($this->hasViolationFor($violations, 'photos'));
    }

    public function testFourPhotosPassValidation(): void
    {
        $data = $this->newData(4);
        $violations = $this->validator()->validate($data);

        self::assertFalse($this->hasViolationFor($violations, 'photos'));
    }

    public function testFivePhotosFailValidation(): void
    {
        $data = $this->newData(5);
        $violations = $this->validator()->validate($data);

        self::assertTrue($this->hasViolationFor($violations, 'photos'));
    }

    public function testEmptySlotsAreIgnoredInCount(): void
    {
        // Simulates the wizard pre-seeded form: 4 slots, but only 2 with files.
        $data = new ProductWizardData();
        for ($i = 0; $i < 4; ++$i) {
            $photo = new ProductWizardPhotoData();
            $photo->angle = PhotoAngle::Front;
            $photo->file = $i < 2 ? $this->fakeUpload() : null;
            $data->photos->add($photo);
        }

        self::assertCount(2, $data->getUploadedPhotos());
        $violations = $this->validator()->validate($data);
        self::assertFalse($this->hasViolationFor($violations, 'photos'));
    }

    private function newData(int $photoCount): ProductWizardData
    {
        $data = new ProductWizardData();
        for ($i = 0; $i < $photoCount; ++$i) {
            $photo = new ProductWizardPhotoData();
            $photo->angle = PhotoAngle::Front;
            $photo->file = $this->fakeUpload();
            $data->photos->add($photo);
        }

        return $data;
    }

    private function fakeUpload(): UploadedFile
    {
        $tmp = \tempnam(\sys_get_temp_dir(), 'wiz').'.jpg';
        \file_put_contents($tmp, 'fake');

        return new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true);
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * Checks for the exact `photos` path — that is the Callback violation
     * raised by `validatePhotoCount`. Sub-form violations (e.g. `photos[0].file`
     * for the File constraint on each upload) are intentionally ignored.
     *
     * @param iterable<\Symfony\Component\Validator\ConstraintViolationInterface> $violations
     */
    private function hasViolationFor(iterable $violations, string $path): bool
    {
        foreach ($violations as $v) {
            if ($v->getPropertyPath() === $path) {
                return true;
            }
        }

        return false;
    }
}
