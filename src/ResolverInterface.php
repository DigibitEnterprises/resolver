<?php declare(strict_types=1);

namespace Digibit\Resolver;

interface ResolverInterface
{
    /**
     * Whether this resolver can handle the given subject.
     * Static so the dispatcher can check applicability before paying the
     * cost of instantiation. $subject is the raw value being dispatched on —
     * inspect its type, its content, or both; whatever the resolver needs.
     *
     * Keep this cheap: it may be called once per registered resolver,
     * per resolve()/supports()/resolverClassFor() call, until one matches.
     */
    public static function supports(mixed $subject): bool;

    /**
     * Resolve the given subject.
     *
     * May throw SubjectDeclinedException if, having matched via supports(),
     * closer inspection of the subject reveals this resolver can't actually
     * handle it after all.
     */
    public function resolve(mixed $subject): mixed;
}
