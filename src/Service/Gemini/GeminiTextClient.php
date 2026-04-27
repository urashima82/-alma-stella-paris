<?php

declare(strict_types=1);

namespace App\Service\Gemini;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Multimodal text-generation client (Gemini 2.5 Flash, vision → structured JSON).
 *
 * Distinct from GeminiImageClient: uses responseSchema to force a strict JSON output
 * matching a caller-supplied schema. Used for AI content filling (M17).
 */
final class GeminiTextClient
{
    private const string ENDPOINT_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const int MAX_RETRIES = 3;
    private const array BACKOFF_SECONDS = [2, 4, 8];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $geminiApiKey,
        private readonly string $modelName,
    ) {
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * @param string[]             $imageBase64Array base64-encoded images (JPEG/PNG)
     * @param array<string, mixed> $responseSchema   JSON schema (responseSchema for structured output)
     */
    public function generate(string $prompt, array $imageBase64Array, array $responseSchema): GeminiTextResponse
    {
        $payload = $this->buildPayload($prompt, $imageBase64Array, $responseSchema);

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; ++$attempt) {
            try {
                return $this->doRequest($payload);
            } catch (GeminiApiException $e) {
                if ($e->getHttpStatusCode() !== 429 || $attempt >= self::MAX_RETRIES) {
                    throw $e;
                }

                $delay = self::BACKOFF_SECONDS[$attempt];
                $this->logger->warning('Gemini text API rate limited (429), retrying in {delay}s (attempt {attempt}/{max})', [
                    'delay' => $delay,
                    'attempt' => $attempt + 1,
                    'max' => self::MAX_RETRIES,
                ]);
                \sleep($delay);
            }
        }

        throw new GeminiApiException('Max retries exceeded');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function doRequest(array $payload): GeminiTextResponse
    {
        $endpoint = \sprintf(self::ENDPOINT_TEMPLATE, $this->modelName);

        $response = $this->httpClient->request('POST', $endpoint, [
            'headers' => [
                'x-goog-api-key' => $this->geminiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 120,
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $body = $response->getContent(false);
            throw new GeminiApiException(\sprintf('Gemini text API error: %s', $body), $statusCode);
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return $this->parseResponse($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseResponse(array $data): GeminiTextResponse
    {
        $candidates = $data['candidates'] ?? [];
        if ($candidates === []) {
            throw new GeminiApiException('No candidates in Gemini text response');
        }

        $parts = $candidates[0]['content']['parts'] ?? [];

        $rawText = '';
        foreach ($parts as $part) {
            if (isset($part['text']) && \is_string($part['text'])) {
                $rawText .= $part['text'];
            }
        }

        if ($rawText === '') {
            $finishReason = $candidates[0]['finishReason'] ?? 'unknown';
            throw new GeminiApiException(\sprintf('No text in Gemini response (reason: %s)', $finishReason));
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = \json_decode($rawText, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new GeminiApiException(\sprintf('Gemini returned non-JSON content: %s', \substr($rawText, 0, 300)), 0, null, $e);
        }

        return new GeminiTextResponse(
            structuredPayload: $payload,
            rawText: $rawText,
            requestId: $data['modelVersion'] ?? null,
        );
    }

    /**
     * @param string[]             $imageBase64Array
     * @param array<string, mixed> $responseSchema
     *
     * @return array<string, mixed>
     */
    private function buildPayload(string $prompt, array $imageBase64Array, array $responseSchema): array
    {
        $parts = [
            ['text' => $prompt],
        ];

        foreach ($imageBase64Array as $base64) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => 'image/jpeg',
                    'data' => $base64,
                ],
            ];
        }

        return [
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $responseSchema,
                'temperature' => 0.7,
            ],
        ];
    }
}
