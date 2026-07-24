<?php declare(strict_types=1);

namespace Digibit\Resolver;

/**
 * A catch-all resolver whose supports() always returns true. Register it
 * at the lowest priority (e.g. PHP_INT_MIN) to guarantee it only runs as
 * a last resort — anything registered after it at an equal or lower
 * priority would otherwise be permanently shadowed.
 */
final class DefaultResolver implements ResolverInterface
{
    public function __construct(private readonly \Closure $default)
    {
    }

    public static function supports(mixed $subject): bool
    {
        return true;
    }

    public function resolve(mixed $subject): mixed
    {
        return ($this->default)($subject);
    }
}
