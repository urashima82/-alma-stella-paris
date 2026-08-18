<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderConfirmationOutcome
{
    /** Order confirmed as-is. */
    case Confirmed;

    /** Order confirmed after refunding the conflicting (already sold) items. */
    case ConfirmedPartialRefund;

    /** Every item was already sold: payment fully refunded, order cancelled. */
    case CancelledFullConflict;

    /** Another flow (scheduler or concurrent request) already confirmed it. */
    case AlreadyProcessed;

    /** The order was cancelled before this confirmation attempt. */
    case AlreadyCancelled;
}
