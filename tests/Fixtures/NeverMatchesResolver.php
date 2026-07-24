<?php declare(strict_types=1);

namespace Digibit\Resolver\Tests\Fixtures;

use Digibit\Resolver\ResolverInterface;

final class NeverMatchesResolver implements ResolverInterface
{
    public static int $constructions = 0;

    public function __construct()
    {
        self::$constructions++;
    }

    public static function supports(mixed $subject): bool
    {
        return false;
    }

    public function resolve(mixed $subject): mixed
    {
        throw new \LogicException('should never be called');
    }

    public static function reset(): void
    {
        self::$constructions = 0;
    }
}
