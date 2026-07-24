<?php declare(strict_types=1);

namespace Digibit\Resolver;

/**
 * Available for a resolver to throw from its own resolve() when, having
 * matched via supports(), closer inspection of the subject reveals it
 * can't actually be handled after all. Not thrown by the dispatcher
 * itself, and not caught or retried by it either — it propagates
 * straight to the caller.
 */
final class SubjectDeclinedException extends ResolverException
{
    public static function by(string $resolverClass, mixed $subject): self
    {
        return new self($resolverClass . ' matched via supports() but declined subject of type ' . get_debug_type($subject));
    }
}
