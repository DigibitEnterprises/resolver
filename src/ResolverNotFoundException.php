<?php declare(strict_types=1);

namespace Digibit\Resolver;

/**
 * Thrown by ResolverDispatcher::resolve() when no registered resolver's
 * supports() claims the subject at all.
 */
final class ResolverNotFoundException extends ResolverException
{
    public static function forSubject(mixed $subject): self
    {
        return new self('No resolver registered for subject of type ' . get_debug_type($subject));
    }
}
