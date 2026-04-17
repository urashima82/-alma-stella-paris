<?php

declare(strict_types=1);

namespace App\Service\Gemini;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiImageClient
{
    private const string ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-image-generation:generateContent';
    private const int MAX_RETRIES = 3;
    private const array BACKOFF_SECONDS = [2, 4, 8];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $geminiApiKey,
    ) {
    }

    /**
     * @param string[] $imageBase64Array base64-encoded images (JPEG/PNG)
     */
    public function generate(string $prompt, array $imageBase64Array): GeminiResponse
    {
        $payload = $this->buildPayload($prompt, $imageBase64Array);

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; ++$attempt) {
            try {
                return $this->doRequest($payload);
            } catch (GeminiApiException $e) {
                if ($e->getHttpStatusCode() !== 429 || $attempt >= self::MAX_RETRIES) {
                    throw $e;
                }

                $delay = self::BACKOFF_SECONDS[$attempt];
                $this->logger->warning('Gemini API rate limited (429), retrying in {delay}s (attempt {attempt}/{max})', [
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
    private function doRequest(array $payload): GeminiResponse
    {
        $response = $this->httpClient->request('POST', self::ENDPOINT, [
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
            throw new GeminiApiException(\sprintf('Gemini API error: %s', $body), $statusCode);
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return $this->parseResponse($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseResponse(array $data): GeminiResponse
    {
        $candidates = $data['candidates'] ?? [];
        if ($candidates === []) {
            throw new GeminiApiException('No candidates in Gemini response');
        }

        $parts = $candidates[0]['content']['parts'] ?? [];

        foreach ($parts as $part) {
            if (isset($part['inlineData'])) {
                return new GeminiResponse(
                    imageData: $part['inlineData']['data'],
                    mimeType: $part['inlineData']['mimeType'] ?? 'image/png',
                    requestId: $data['modelVersion'] ?? null,
                );
            }
        }

        throw new GeminiApiException('No image data found in Gemini response');
    }

    /**
     * @param string[] $imageBase64Array
     *
     * @return array<string, mixed>
     */
    private function buildPayload(string $prompt, array $imageBase64Array): array
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
                'responseModalities' => ['IMAGE'],
            ],
        ];
    }
}
