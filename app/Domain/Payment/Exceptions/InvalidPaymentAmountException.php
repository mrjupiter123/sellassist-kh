<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use DomainException;

final class InvalidPaymentAmountException extends DomainException {}
