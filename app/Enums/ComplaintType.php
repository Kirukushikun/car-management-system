<?php

namespace App\Enums;

/**
 * "Type of Complaint/Problem" from Part I of the paper CAR form.
 */
enum ComplaintType: string
{
    case Product = 'product';
    case Service = 'service';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product Nonconformity',
            self::Service => 'Service Nonconformity',
            self::Other => 'Others',
        };
    }
}
