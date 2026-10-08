<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel;

/**
 * Value Object: Address
 */
final class Address
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $phone,
        public readonly string $line1,
        public readonly ?string $line2,
        public readonly string $ward,
        public readonly string $district,
        public readonly string $province,
        public readonly string $country = 'VN',
        public readonly ?string $postalCode = null,
        public readonly ?string $type = null, // billing, shipping, pickup
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            fullName: (string) ($data['full_name'] ?? $data['fullName'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            line1: (string) ($data['line1'] ?? $data['address_line1'] ?? ''),
            line2: $data['line2'] ?? $data['address_line2'] ?? null,
            ward: (string) ($data['ward'] ?? $data['ward_name'] ?? ''),
            district: (string) ($data['district'] ?? $data['district_name'] ?? ''),
            province: (string) ($data['province'] ?? $data['province_name'] ?? ''),
            country: (string) ($data['country'] ?? 'VN'),
            postalCode: $data['postal_code'] ?? $data['postalCode'] ?? null,
            type: $data['type'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'full_name' => $this->fullName,
            'phone' => $this->phone,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'ward' => $this->ward,
            'district' => $this->district,
            'province' => $this->province,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
            'type' => $this->type,
        ];
    }

    public function getFullAddress(): string
    {
        $parts = [$this->line1];
        if ($this->line2) $parts[] = $this->line2;
        $parts[] = $this->ward;
        $parts[] = $this->district;
        $parts[] = $this->province;
        if ($this->postalCode) $parts[] = $this->postalCode;
        return implode(', ', $parts);
    }

    public function getShortAddress(): string
    {
        return implode(', ', [$this->ward, $this->district, $this->province]);
    }
}