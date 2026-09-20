<?php

declare(strict_types=1);

namespace App\Tests\Form\Admin;

use App\Entity\SourcePhoto;
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
        // A browser can submit an entry whose file never made it (cancelled
        // picker, discarded card): those entries must not count towards the
        // 2..4 bound.
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

    /**
     * The photo tray builds its entries client-side, so the CollectionType runs
     * with `allow_add`/`allow_delete` and routes them through these two.
     */
    public function testAddAndRemovePhotoDriveTheCollection(): void
    {
        $data = new ProductWizardData();
        $photo = new ProductWizardPhotoData();

        $data->addPhoto($photo);
        $data->addPhoto($photo);
        self::assertCount(1, $data->photos);

        $data->removePhoto($photo);
        self::assertCount(0, $data->photos);
    }

    public function testBoundsFollowTheSourcePhotoContract(): void
    {
        // The wizard and the edit workspace must not drift apart: both read the
        // bounds off the entity.
        $tooFew = $this->newData(SourcePhoto::MIN_PER_PRODUCT - 1);
        $tooMany = $this->newData(SourcePhoto::MAX_PER_PRODUCT + 1);

        self::assertTrue($this->hasViolationFor($this->validator()->validate($tooFew), 'photos'));
        self::assertTrue($this->hasViolationFor($this->validator()->validate($tooMany), 'photos'));
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
