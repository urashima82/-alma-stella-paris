<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Product;
use App\Entity\ProductContentSuggestion;
use App\Enum\ContentSuggestionStatus;
use App\Enum\GeminiOperation;
use App\Message\FillProductContentMessage;
use App\Repository\ProductContentSuggestionRepository;
use App\Repository\ProductRepository;
use App\Service\Content\ContentSuggestionException;
use App\Service\Content\ProductContentFiller;
use App\Service\Gemini\BudgetGuard;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsMessageHandler]
final class FillProductContentHandler
{
    public function __construct(
        private readonly RateLimiterFactory $geminiApiLimiter,
        private readonly BudgetGuard $budgetGuard,
        private readonly ProductRepository $productRepository,
        private readonly ProductContentSuggestionRepository $suggestionRepository,
        private readonly ProductContentFiller $contentFiller,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FillProductContentMessage $message): void
    {
        $limiter = $this->geminiApiLimiter->create('gemini_api');
        if (!$limiter->consume(1)->isAccepted()) {
            throw new RecoverableMessageHandlingException('Gemini API rate limit reached, retrying later.');
        }

        $this->budgetGuard->ensureBudgetAvailable();

        $product = $this->productRepository->find($message->productId);
        if ($product === null) {
            $this->logger->error('Product not found for content filling', ['productId' => $message->productId]);

            return;
        }

        $suggestion = $this->resolveOrCreateSuggestion($message, $product);
        $suggestion->setContextSnapshot($this->contentFiller->buildContextSnapshot($product));
        $suggestion->setAdditionalContext($message->additionalContext);

        try {
            $result = $this->contentFiller->fill($product, $message->additionalContext);
        } catch (\Throwable $e) {
            // Catch Throwable so any unexpected failure (HTTP timeout, parse error,
            // missing source files, etc.) flips the suggestion to Rejected with a
            // readable error rather than leaving it stuck in Pending forever.
            $suggestion->setStatus(ContentSuggestionStatus::Rejected);
            $suggestion->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            $this->logger->error('Content suggestion failed', [
                'productId' => $message->productId,
                'suggestionId' => $suggestion->getId(),
                'error' => $e->getMessage(),
                'httpStatus' => $e instanceof ContentSuggestionException ? $e->getHttpStatusCode() : null,
                'exception' => $e,
            ]);

            return;
        }

        $suggestion->setNameEn($result->nameEn);
        $suggestion->setNameFr($result->nameFr);
        $suggestion->setDescriptionEn($result->descriptionEn);
        $suggestion->setDescriptionFr($result->descriptionFr);
        // Generating → Pending: the worker has produced the content, it is now
        // up to the manager to review, edit, approve or reject.
        $suggestion->setStatus(ContentSuggestionStatus::Pending);
        $suggestion->setModelUsed($result->modelName);
        $suggestion->setRequestId($result->requestId);
        $suggestion->setErrorMessage(null);

        $this->budgetGuard->recordCall($result->estimatedCostUsd, GeminiOperation::TextFill);

        $this->entityManager->flush();

        $this->logger->info('Content suggestion ready for review', [
            'productId' => $message->productId,
            'suggestionId' => $suggestion->getId(),
        ]);
    }

    private function resolveOrCreateSuggestion(FillProductContentMessage $message, Product $product): ProductContentSuggestion
    {
        if ($message->suggestionId !== null) {
            $suggestion = $this->suggestionRepository->find($message->suggestionId);
            if ($suggestion !== null) {
                return $suggestion;
            }
            $this->logger->warning('FillProductContentMessage references missing suggestionId, creating new entity', [
                'suggestionId' => $message->suggestionId,
            ]);
        }

        $suggestion = new ProductContentSuggestion();
        $suggestion->setProduct($product);
        $suggestion->setStatus(ContentSuggestionStatus::Generating);
        $this->entityManager->persist($suggestion);
        $this->entityManager->flush();

        return $suggestion;
    }
}
