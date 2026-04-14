<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Product;
use App\Service\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ImageUploadSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<class-string, list<array{getter: string, setter: string, maxWidth: int, maxHeight: int}>>
     */
    private const ENTITY_CONFIG = [
        Product::class => [
            ['getter' => 'getThumbnail', 'setter' => 'setThumbnail', 'maxWidth' => 600, 'maxHeight' => 750],
            ['getter' => 'getWornPhoto', 'setter' => 'setWornPhoto', 'maxWidth' => 800, 'maxHeight' => 1000],
            ['getter' => 'getContextPhoto', 'setter' => 'setContextPhoto', 'maxWidth' => 800, 'maxHeight' => 1000],
        ],
    ];

    public function __construct(
        private readonly ImageProcessor $imageProcessor,
        private readonly EntityManagerInterface $em,
        private readonly string $uploadDir,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => 'onPostPersist',
            AfterEntityUpdatedEvent::class => 'onPostUpdate',
        ];
    }

    /** @param AfterEntityPersistedEvent<object> $event */
    public function onPostPersist(AfterEntityPersistedEvent $event): void
    {
        $this->processImage($event->getEntityInstance());
    }

    /** @param AfterEntityUpdatedEvent<object> $event */
    public function onPostUpdate(AfterEntityUpdatedEvent $event): void
    {
        $this->processImage($event->getEntityInstance());
    }

    private function processImage(object $entity): void
    {
        $configs = $this->getConfigs($entity);
        if ($configs === []) {
            return;
        }

        $flushed = false;

        foreach ($configs as $config) {
            /** @var string|null $filename */
            $filename = $entity->{$config['getter']}();
            if ($filename === null) {
                continue;
            }

            $sourcePath = $this->uploadDir.'/'.$filename;
            if (!\file_exists($sourcePath)) {
                continue;
            }

            // Already WebP — just resize if needed
            if (\str_ends_with(\strtolower($filename), '.webp')) {
                $this->imageProcessor->process($sourcePath, $sourcePath, $config['maxWidth'], $config['maxHeight']);

                continue;
            }

            // Convert to WebP
            $webpFilename = \pathinfo($filename, \PATHINFO_FILENAME).'.webp';
            $targetPath = $this->uploadDir.'/'.$webpFilename;

            $this->imageProcessor->process($sourcePath, $targetPath, $config['maxWidth'], $config['maxHeight']);

            // Remove original file
            \unlink($sourcePath);

            // Update entity with new filename
            $entity->{$config['setter']}($webpFilename);
            $flushed = true;
        }

        if ($flushed) {
            $this->em->flush();
        }
    }

    /**
     * @return list<array{getter: string, setter: string, maxWidth: int, maxHeight: int}>
     */
    private function getConfigs(object $entity): array
    {
        foreach (self::ENTITY_CONFIG as $class => $configs) {
            if ($entity instanceof $class) {
                return $configs;
            }
        }

        return [];
    }
}
