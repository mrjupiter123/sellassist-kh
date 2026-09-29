<?php

declare(strict_types=1);

namespace App\Domain\OrderActivity\Enums;

enum OrderActivityType: string
{
    case Created = 'created';
    case Amended = 'amended';
    case StatusChanged = 'status_changed';
    case PaymentRecorded = 'payment_recorded';
    case RefundRecorded = 'refund_recorded';
    case ReturnRecorded = 'return_recorded';
    case ShipmentCreated = 'shipment_created';
    case ShipmentStatusChanged = 'shipment_status_changed';
    case CodRemitted = 'cod_remitted';
    case ShipmentSubmitted = 'shipment_submitted';
}
