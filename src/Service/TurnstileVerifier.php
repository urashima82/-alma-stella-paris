<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies Cloudflare Turnstile challenge responses.
 *
 * When no secret key is configured (dev environment), verification
 * is skipped and all requests are considered valid.
 */
final class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $turnstileSecretKey,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->turnstileSecretKey !== '';
    }

    /**
     * Verifies a Turnstile response token.
     *
     * Returns true if the token is valid, or if Turnstile is not configured.
     */
    public function verify(string $token, ?string $remoteIp = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if ($token === '') {
            $this->logger->warning('Turnstile: empty response token received.');

            return false;
        }

        $payload = [
            'secret' => $this->turnstileSecretKey,
            'response' => $token,
        ];

        if ($remoteIp !== null) {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => $payload,
            ]);

            $data = $response->toArray();

            if ($data['success'] ?? false) {
                return true;
            }

            $this->logger->warning('Turnstile verification failed.', [
                'error-codes' => $data['error-codes'] ?? [],
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Turnstile API error: '.$e->getMessage());

            // Fail open: if the API is unreachable, let the user through
            // rather than blocking legitimate submissions.
            return true;
        }
    }
}
