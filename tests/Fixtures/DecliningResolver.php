<?php declare(strict_types=1);

namespace Digibit\Resolver\Tests\Fixtures;

use Digibit\Resolver\ResolverInterface;
use Digibit\Resolver\SubjectDeclinedException;

final class DecliningResolver implements ResolverInterface
{
    public static function supports(mixed $subject): bool
    {
        return true;
    }

    public function resolve(mixed $subject): mixed
    {
        throw SubjectDeclinedException::by(self::class, $subject);
    }
}
