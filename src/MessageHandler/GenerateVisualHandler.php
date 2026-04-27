<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\GeneratedVisual;
use App\Entity\Product;
use App\Enum\VisualStatus;
use App\Enum\VisualWorkflowStatus;
use App\Message\GenerateVisualMessage;
use App\Repository\GeneratedVisualRepository;
use App\Repository\ProductRepository;
use App\Service\Gemini\BudgetGuard;
use App\Service\Generator\VisualGenerationException;
use App\Service\Generator\VisualGeneratorRouter;
use App\Service\Prompt\PromptBuilder;
use App\Service\Visual\ImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsMessageHandler]
final class GenerateVisualHandler
{
    public function __construct(
        private readonly RateLimiterFactory $geminiApiLimiter,
        private readonly BudgetGuard $budgetGuard,
        private readonly ProductRepository $productRepository,
        private readonly GeneratedVisualRepository $visualRepository,
        private readonly PromptBuilder $promptBuilder,
        private readonly VisualGeneratorRouter $generatorRouter,
        private readonly ImageStorage $imageStorage,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateVisualMessage $message): void
    {
        $limiter = $this->geminiApiLimiter->create('gemini_api');
        if (!$limiter->consume(1)->isAccepted()) {
            throw new RecoverableMessageHandlingException('Gemini API rate limit reached, retrying later.');
        }

        $this->budgetGuard->ensureBudgetAvailable();

        $generator = $this->generatorRouter->for($message->type);

        $product = $this->productRepository->find($message->productId);
        if ($product === null) {
            $this->logger->error('Product not found for visual generation', ['productId' => $message->productId]);

            return;
        }

        $sourcePhotos = $product->getSourcePhotos();
        if ($sourcePhotos->isEmpty()) {
            $this->logger->error('No source photos for product', ['productId' => $message->productId]);

            return;
        }

        // Resolve the GeneratedVisual entity: either the one pre-created by the
        // controller (preferred — visualId is set) or fall back to creating a new
        // one for legacy queued messages.
        $visual = $this->resolveOrCreateVisual($message, $product);

        $promptResult = $this->promptBuilder->buildForVisual($product, $message->type);

        $sourcesBase64 = [];
        foreach ($sourcePhotos as $sourcePhoto) {
            $content = $this->imageStorage->read($sourcePhoto->getPath());
            $sourcesBase64[] = \base64_encode($content);
        }

        try {
            $result = $generator->generate($promptResult->content, $sourcesBase64, $message->type);
        } catch (\Throwable $e) {
            // Catch Throwable (not just VisualGenerationException) so any unexpected
            // crash — HTTP timeout, parse error, OOM, etc. — still flips the visual
            // to Failed with a readable error_message rather than leaving it stuck
            // in `generating` forever.
            $visual->setPromptUsed($promptResult->content);
            $visual->setCategoryPromptVersion($promptResult->categoryPromptVersion);
            $visual->setStatus(VisualStatus::Failed);
            $visual->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            $this->logger->error('Visual generation failed', [
                'productId' => $message->productId,
                'type' => $message->type->value,
                'variant' => $message->variantNumber,
                'visualId' => $visual->getId(),
                'error' => $e->getMessage(),
                'httpStatus' => $e instanceof VisualGenerationException ? $e->getHttpStatusCode() : null,
                'exception' => $e,
            ]);

            return;
        }

        $path = $this->imageStorage->storeGeneratedVisual(
            $result->imageBase64,
            $product,
            $message->type,
            $message->variantNumber,
        );

        $visual->setPath($path);
        $visual->setPromptUsed($promptResult->content);
        $visual->setCategoryPromptVersion($promptResult->categoryPromptVersion);
        $visual->setStatus(VisualStatus::PendingReview);
        $visual->setGeminiRequestId($result->requestId);
        $visual->setModelUsed($result->modelName);
        $visual->setErrorMessage(null);

        $this->budgetGuard->recordCall($result->estimatedCostUsd);

        if ($this->allVariantsGenerated($product)) {
            $product->setVisualStatus(VisualWorkflowStatus::ReadyForReview);
        }

        $this->entityManager->flush();

        $this->logger->info('Visual generated successfully', [
            'productId' => $message->productId,
            'type' => $message->type->value,
            'variant' => $message->variantNumber,
            'visualId' => $visual->getId(),
            'usedFallback' => $promptResult->usedFallback,
        ]);
    }

    private function resolveOrCreateVisual(GenerateVisualMessage $message, Product $product): GeneratedVisual
    {
        if ($message->visualId !== null) {
            $visual = $this->visualRepository->find($message->visualId);
            if ($visual !== null) {
                return $visual;
            }
            $this->logger->warning('GenerateVisualMessage references missing visualId, creating new entity', [
                'visualId' => $message->visualId,
            ]);
        }

        $visual = new GeneratedVisual();
        $visual->setProduct($product);
        $visual->setType($message->type);
        $visual->setVariant($message->variantNumber);
        $visual->setStatus(VisualStatus::Generating);
        $this->entityManager->persist($visual);
        $this->entityManager->flush();

        return $visual;
    }

    private function allVariantsGenerated(Product $product): bool
    {
        $total = 0;
        $generated = 0;

        foreach (\App\Enum\VisualType::cases() as $type) {
            for ($v = 1; $v <= 3; ++$v) {
                ++$total;
                foreach ($product->getGeneratedVisuals() as $visual) {
                    if ($visual->getType() === $type
                        && $visual->getVariant() === $v
                        && $visual->getStatus() !== VisualStatus::Generating) {
                        ++$generated;
                        break;
                    }
                }
            }
        }

        return $generated >= $total;
    }
}
