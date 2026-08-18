<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Analytics\PageViewCollector;
use App\Enum\StatDimension;
use App\Repository\PageViewStatRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Wires the audience counters. The work is split across two events on purpose:
 *
 *  - `kernel.response` DECIDES. {@see PageViewCollector} reads the session to
 *    exclude admin traffic, and a session can only be read while it is open.
 *    Symfony's session listener closes it at RESPONSE priority -1000, and by
 *    `kernel.terminate` the response is flushed, so reopening it throws
 *    ("headers have already been sent") — which would have swallowed every
 *    request carrying a session cookie, i.e. all logged-in traffic, in silence.
 *    Hence RESPONSE at -900: after the response mutators, before the session
 *    save.
 *  - `kernel.terminate` WRITES, so the statement never sits in the visitor's
 *    latency.
 *
 * Measurement must never surface an error to a visitor: both halves swallow and
 * log. Losing a counter is not worth an incident.
 */
final class PageViewSubscriber implements EventSubscriberInterface
{
    /** Request attribute carrying the decided entries from RESPONSE to TERMINATE. */
    private const string ENTRIES_ATTRIBUTE = '_asp_page_view_entries';

    public function __construct(
        private readonly PageViewCollector $collector,
        private readonly PageViewStatRepository $stats,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before the session listener closes the session (-1000).
            KernelEvents::RESPONSE => ['onKernelResponse', -900],
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Sub-requests are not page views. TERMINATE is main-request only,
        // RESPONSE is not.
        if (!$event->isMainRequest()) {
            return;
        }

        try {
            $entries = $this->collector->collect($event->getRequest(), $event->getResponse());
            if ($entries !== null) {
                $event->getRequest()->attributes->set(self::ENTRIES_ATTRIBUTE, $entries);
            }
        } catch (\Throwable $e) {
            $this->log($e);
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        // Written by onKernelResponse one event earlier, or absent.
        /** @var list<array{StatDimension, string}>|null $entries */
        $entries = $event->getRequest()->attributes->get(self::ENTRIES_ATTRIBUTE);
        if ($entries === null || $entries === []) {
            return;
        }

        try {
            $this->stats->record(new \DateTimeImmutable('today'), $entries);
        } catch (\Throwable $e) {
            $this->log($e);
        }
    }

    private function log(\Throwable $e): void
    {
        $this->logger->warning('Audience measurement failed: {message}', [
            'message' => $e->getMessage(),
            'exception' => $e,
        ]);
    }
}
