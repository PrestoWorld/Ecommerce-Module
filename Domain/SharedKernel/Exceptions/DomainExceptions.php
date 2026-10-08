<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Exceptions;

class DomainException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class InvalidStateTransition extends DomainException
{
    public static function from(string $entity, string $from, string $to): self
    {
        return new self("Invalid state transition for {$entity}: {$from} -> {$to}", 4009);
    }
}

class EntityNotFound extends DomainException
{
    public static function for(string $entity, string $identifier): self
    {
        return new self("{$entity} not found: {$identifier}", 4004);
    }
}

class BusinessRuleViolation extends DomainException
{
    public function __construct(
        string $message,
        public readonly string $rule,
        public readonly array $context = [],
    ) {
        parent::__construct($message, 4003);
    }
}

class ConcurrencyException extends DomainException
{
    public static function for(string $entity, string $id): self
    {
        return new self("Concurrency conflict for {$entity} {$id}", 4009);
    }
}