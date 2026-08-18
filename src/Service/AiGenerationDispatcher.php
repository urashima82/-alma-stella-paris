<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GeneratedVisual;
use App\Entity\ProductContentSuggestion;
use App\Enum\ContentSuggestionStatus;
use App\Enum\VisualStatus;
use App\Message\FillProductContentMessage;
use App\Message\GenerateVisualMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches AI generation messages while keeping the tracking entity honest.
 *
 * Every dispatch site flushes a row in `Generating` status BEFORE handing the
 * message to the transport. If the dispatch then fails (transport down,
 * messenger table missing, …), that row would stay `Generating` forever: the
 * admin UI shows an eternal spinner and disables the generate button, with no
 * message ever coming to resolve it. This wrapper flips the entity to its
 * failure status before re-throwing, so a dispatch failure is visible and
 * retryable instead of a dead end.
 */
final class AiGenerationDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function dispatchContentFill(ProductContentSuggestion $suggestion, FillProductContentMessage $message): void
    {
        try {
            $this->messageBus->dispatch($message);
        } catch (\Throwable $e) {
            $suggestion->setStatus(ContentSuggestionStatus::Rejected);
            $suggestion->setErrorMessage('Envoi en file impossible : '.$e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }
    }

    public function dispatchVisualGeneration(GeneratedVisual $visual, GenerateVisualMessage $message): void
    {
        try {
            $this->messageBus->dispatch($message);
        } catch (\Throwable $e) {
            $visual->setStatus(VisualStatus::Failed);
            $visual->setErrorMessage('Envoi en file impossible : '.$e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }
    }
}
