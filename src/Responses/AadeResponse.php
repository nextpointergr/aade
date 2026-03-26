<?php

namespace NextPointer\Aade\Responses;

class AadeResponse
{
    protected array $parsed;
    protected string $raw;

    public function __construct(array $parsed, string $raw)
    {
        $this->parsed = $parsed;
        $this->raw = $raw;
    }

    public function toArray(): array
    {
        return $this->parsed;
    }

    public function original(): string
    {
        return $this->raw;
    }

    public function success(): bool
    {
        return $this->parsed['success'] ?? false;
    }

    public function data(): array
    {
        return $this->parsed['data'] ?? [];
    }

    public function reason(): ?string
    {
        return $this->parsed['reason'] ?? null;
    }

    public function __get($key)
    {
        return $this->parsed[$key] ?? null;
    }


    public function getTaxId(): ?string
    {
        return $this->data()['tax_id'] ?? null;
    }

    public function getName(): ?string
    {
        return $this->data()['name'] ?? null;
    }

    public function getAddress(): ?string
    {
        return $this->data()['address'] ?? null;
    }

    public function getAddressNumber(): ?string
    {
        return $this->data()['address_number'] ?? null;
    }

    public function getCity(): ?string
    {
        return $this->data()['city'] ?? null;
    }

    public function getPostalCode(): ?string
    {
        return $this->data()['postal_code'] ?? null;
    }

    public function getTaxOffice(): ?string
    {
        return $this->data()['tax_office'] ?? null;
    }

    public function getStatus(): ?string
    {
        return $this->data()['status'] ?? null;
    }

    public function getActivities(): array
    {
        return $this->data()['activities'] ?? [];
    }

    public function getMainActivity(): ?array
    {
        foreach ($this->getActivities() as $activity) {
            if ($activity['type'] == '1') {
                return $activity;
            }
        }

        return null;
    }
}
