<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use DomainException;

class OrderHasActiveShipmentException extends DomainException {}
