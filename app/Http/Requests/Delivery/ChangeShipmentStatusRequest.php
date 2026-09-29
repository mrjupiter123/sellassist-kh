<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeShipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delivery.update') === true;
    }

    public function rules(): array
    {
        /** @var Shipment $shipment */
        $shipment = $this->route('shipment');

        return [
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'tracking_number' => [
                'nullable', 'string', 'max:255',
                Rule::unique('shipments', 'tracking_number')
                    ->where('delivery_provider_id', $shipment->delivery_provider_id)
                    ->ignore($shipment),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
