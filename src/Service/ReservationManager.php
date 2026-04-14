<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ReservationManager
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Reserve a product for the current session.
     * Returns true if the reservation was created or already exists for this session.
     * Returns false if the product is reserved by someone else or is sold out.
     */
    public function reserve(Product $product): bool
    {
        if ($product->isSoldOut()) {
            return false;
        }

        $sessionId = $this->getSessionId();
        $existing = $this->reservationRepository->findOneBy(['product' => $product]);

        if ($existing !== null) {
            // Expired reservation — remove it regardless of owner
            if ($existing->isExpired()) {
                $this->entityManager->remove($existing);
                $this->entityManager->flush();
            } elseif ($existing->isOwnedBy($sessionId)) {
                // Active reservation owned by this session — keep it
                return true;
            } else {
                // Active reservation owned by someone else
                return false;
            }
        }

        $reservation = Reservation::create($product, $sessionId);
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $this->logger->info('Product {id} reserved for session {session} until {expires}.', [
            'id' => $product->getId(),
            'session' => \substr($sessionId, 0, 8).'...',
            'expires' => $reservation->getExpiresAt()->format('H:i:s'),
        ]);

        return true;
    }

    /**
     * Release the reservation for a specific product (any session).
     */
    public function release(Product $product): void
    {
        $this->reservationRepository->deleteByProduct($product);
    }

    /**
     * Release all reservations for the current session.
     */
    public function releaseCurrentSession(): void
    {
        $this->reservationRepository->deleteBySessionId($this->getSessionId());
    }

    /**
     * Check if a product is reserved by another session (with lazy expiry check).
     */
    public function isReservedByOther(Product $product): bool
    {
        $existing = $this->reservationRepository->findOneBy(['product' => $product]);

        if ($existing === null) {
            return false;
        }

        // Lazy expiry check
        if ($existing->isExpired()) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();

            return false;
        }

        return !$existing->isOwnedBy($this->getSessionId());
    }

    /**
     * Check if a product is reserved by the current session.
     */
    public function isReservedByCurrentSession(Product $product): bool
    {
        $reservation = $this->reservationRepository->findActiveByProduct($product);

        if ($reservation === null) {
            return false;
        }

        return $reservation->isOwnedBy($this->getSessionId());
    }

    /**
     * Extend all active reservations for the current session.
     * Returns the new remaining seconds, or null if no extendable reservations.
     *
     * @return array{remainingSeconds: int, extensionsLeft: int}|null
     */
    public function extendForCurrentSession(): ?array
    {
        $sessionId = $this->getSessionId();
        $reservations = $this->reservationRepository->findActiveBySessionId($sessionId);

        if ($reservations === []) {
            return null;
        }

        $extended = false;
        foreach ($reservations as $reservation) {
            if ($reservation->canExtend()) {
                $reservation->extend();
                $extended = true;
            }
        }

        if (!$extended) {
            return null;
        }

        $this->entityManager->flush();

        $this->logger->info('Extended reservations for session {session}.', [
            'session' => \substr($sessionId, 0, 8).'...',
        ]);

        return [
            'remainingSeconds' => $this->getRemainingSeconds(),
            'extensionsLeft' => $this->getExtensionsLeftForCurrentSession(),
        ];
    }

    /**
     * Get remaining extensions for the current session (minimum across all reservations).
     */
    public function getExtensionsLeftForCurrentSession(): int
    {
        $reservations = $this->reservationRepository->findActiveBySessionId($this->getSessionId());

        if ($reservations === []) {
            return 0;
        }

        $minLeft = Reservation::MAX_EXTENSIONS;
        foreach ($reservations as $reservation) {
            $left = Reservation::MAX_EXTENSIONS - $reservation->getExtensionCount();
            $minLeft = \min($minLeft, $left);
        }

        return $minLeft;
    }

    /**
     * Get the reservation expiry time for the current session's products.
     * Returns the earliest expiry among active reservations, or null if none.
     */
    public function getEarliestExpiryForCurrentSession(): ?\DateTimeImmutable
    {
        $reservations = $this->reservationRepository->findActiveBySessionId($this->getSessionId());

        if ($reservations === []) {
            return null;
        }

        $earliest = null;
        foreach ($reservations as $reservation) {
            if ($earliest === null || $reservation->getExpiresAt() < $earliest) {
                $earliest = $reservation->getExpiresAt();
            }
        }

        return $earliest;
    }

    /**
     * Get remaining seconds for the current session's earliest reservation.
     */
    public function getRemainingSeconds(): int
    {
        $earliest = $this->getEarliestExpiryForCurrentSession();

        if ($earliest === null) {
            return 0;
        }

        return \max(0, $earliest->getTimestamp() - (new \DateTimeImmutable())->getTimestamp());
    }

    /**
     * Get product IDs reserved by other sessions.
     *
     * @return int[]
     */
    public function getReservedProductIdsByOthers(): array
    {
        return $this->reservationRepository->findReservedProductIdsByOthers($this->getSessionId());
    }

    /**
     * Clean up all expired reservations. Returns the number removed.
     */
    public function releaseExpired(): int
    {
        $count = $this->reservationRepository->deleteExpired();

        if ($count > 0) {
            $this->logger->info('Released {count} expired reservation(s).', ['count' => $count]);
        }

        return $count;
    }

    /**
     * Transfer all active reservations from a previous session to the current one.
     * Used during login to preserve reservations across session ID migration.
     */
    public function transferReservations(string $fromSessionId): void
    {
        $toSessionId = $this->getSessionId();

        if ($fromSessionId === $toSessionId) {
            return;
        }

        $count = $this->reservationRepository->transferBySessionId($fromSessionId, $toSessionId);

        if ($count > 0) {
            $this->logger->info('Transferred {count} reservation(s) from session {from} to {to}.', [
                'count' => $count,
                'from' => \substr($fromSessionId, 0, 8).'...',
                'to' => \substr($toSessionId, 0, 8).'...',
            ]);
        }
    }

    private function getSessionId(): string
    {
        return $this->requestStack->getSession()->getId();
    }
}
