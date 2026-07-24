<?php declare(strict_types=1);

namespace Digibit\Resolver;

/**
 * Shared parent for this package's exceptions, for callers who want to
 * catch either failure mode without distinguishing between them.
 */
abstract class ResolverException extends \LogicException
{
}
