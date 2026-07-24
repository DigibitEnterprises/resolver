<?php declare(strict_types=1);

namespace Digibit\Resolver\Tests\Fixtures;

use Digibit\Resolver\ResolverInterface;

final class DateTimeInterfaceResolver implements ResolverInterface
{
    public static int $constructions = 0;

    public function __construct()
    {
        self::$constructions++;
    }

    public static function supports(mixed $subject): bool
    {
        return $subject instanceof \DateTimeInterface;
    }

    public function resolve(mixed $subject): mixed
    {
        return 'interface:' . $subject->format('Y-m-d');
    }

    public static function reset(): void
    {
        self::$constructions = 0;
    }
}
