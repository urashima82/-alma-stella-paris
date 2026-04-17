<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\GeneratedVisual;
use App\Enum\VisualStatus;
use App\Enum\VisualWorkflowStatus;
use App\Message\GenerateVisualMessage;
use App\Repository\ProductRepository;
use App\Service\Gemini\BudgetGuard;
use App\Service\Gemini\GeminiApiException;
use App\Service\Gemini\GeminiImageClient;
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
    private const float ESTIMATED_COST_PER_IMAGE = 0.039;

    public function __construct(
        private readonly RateLimiterFactory $geminiApiLimiter,
        private readonly BudgetGuard $budgetGuard,
        private readonly ProductRepository $productRepository,
        private readonly PromptBuilder $promptBuilder,
        private readonly GeminiImageClient $geminiClient,
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

        $promptResult = $this->promptBuilder->buildForVisual($product, $message->type);

        $sourcesBase64 = [];
        foreach ($sourcePhotos as $sourcePhoto) {
            $content = $this->imageStorage->read($sourcePhoto->getPath());
            $sourcesBase64[] = \base64_encode($content);
        }

        try {
            $response = $this->geminiClient->generate($promptResult->content, $sourcesBase64);
        } catch (GeminiApiException $e) {
            $this->createFailedVisual($message, $promptResult->content, $promptResult->categoryPromptVersion, $e);

            return;
        }

        $path = $this->imageStorage->storeGeneratedVisual(
            $response->imageData,
            $product,
            $message->type,
            $message->variantNumber,
        );

        $visual = new GeneratedVisual();
        $visual->setProduct($product);
        $visual->setType($message->type);
        $visual->setPath($path);
        $visual->setPromptUsed($promptResult->content);
        $visual->setCategoryPromptVersion($promptResult->categoryPromptVersion);
        $visual->setStatus(VisualStatus::PendingReview);
        $visual->setVariant($message->variantNumber);
        $visual->setGeminiRequestId($response->requestId);

        $this->entityManager->persist($visual);

        $this->budgetGuard->recordCall(self::ESTIMATED_COST_PER_IMAGE);

        if ($this->allVariantsGenerated($product)) {
            $product->setVisualStatus(VisualWorkflowStatus::ReadyForReview);
        }

        $this->entityManager->flush();

        $this->logger->info('Visual generated successfully', [
            'productId' => $message->productId,
            'type' => $message->type->value,
            'variant' => $message->variantNumber,
            'usedFallback' => $promptResult->usedFallback,
        ]);
    }

    private function createFailedVisual(
        GenerateVisualMessage $message,
        string $promptUsed,
        int $categoryPromptVersion,
        GeminiApiException $exception,
    ): void {
        $product = $this->productRepository->find($message->productId);
        if ($product === null) {
            return;
        }

        $visual = new GeneratedVisual();
        $visual->setProduct($product);
        $visual->setType($message->type);
        $visual->setPromptUsed($promptUsed);
        $visual->setCategoryPromptVersion($categoryPromptVersion);
        $visual->setStatus(VisualStatus::Failed);
        $visual->setVariant($message->variantNumber);
        $visual->setErrorMessage($exception->getMessage());

        $this->entityManager->persist($visual);
        $this->entityManager->flush();

        $this->logger->error('Visual generation failed', [
            'productId' => $message->productId,
            'type' => $message->type->value,
            'variant' => $message->variantNumber,
            'error' => $exception->getMessage(),
            'httpStatus' => $exception->getHttpStatusCode(),
        ]);
    }

    private function allVariantsGenerated(\App\Entity\Product $product): bool
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
