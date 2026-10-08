<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Events;

/**
 * Base Domain Event
 */
interface DomainEvent
{
    public function eventName(): string;
    public function occurredAt(): \DateTimeImmutable;
    public function payload(): array;
}

abstract class AbstractDomainEvent implements DomainEvent
{
    public function __construct(
        public readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
        public readonly array $payload = [],
    ) {}

    public function eventName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}