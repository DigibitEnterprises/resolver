<?php declare(strict_types=1);

namespace Digibit\Resolver;

final class ResolverDispatcher
{
    /** @var array<class-string, array{factory: \Closure, instance: ?ResolverInterface, priority: int, order: int}> */
    private array $resolvers = [];
    private ?array $sortedKeys = null;
    private int $registrationCounter = 0;

    public function register(string $resolverClass, int $priority = 0): static
    {
        return $this->registerFactory($resolverClass, static fn() => new $resolverClass(), $priority);
    }

    public function registerFactory(string $producedClassName, \Closure $factory, int $priority = 0): static
    {
        if (!is_a($producedClassName, ResolverInterface::class, true)) {
            throw new \LogicException("$producedClassName must implement " . ResolverInterface::class);
        }

        // assigning to an existing key replaces it outright — dedup is automatic
        $this->resolvers[$producedClassName] = [
            'factory' => $factory,
            'instance' => null,
            'priority' => $priority,
            'order' => $this->registrationCounter++,
        ];
        $this->sortedKeys = null;

        return $this;
    }

    public function unregister(string $resolverClass): static
    {
        unset($this->resolvers[$resolverClass]);

        if ($this->sortedKeys !== null) {
            $index = array_search($resolverClass, $this->sortedKeys, true);
            if ($index !== false) {
                array_splice($this->sortedKeys, $index, 1);
            }
        }

        return $this;
    }

    public function has(string $resolverClass): bool
    {
        return isset($this->resolvers[$resolverClass]);
    }

    public function getPriority(string $resolverClass): ?int
    {
        return $this->resolvers[$resolverClass]['priority'] ?? null;
    }

    public function supports(mixed $subject): bool
    {
        return $this->resolverClassFor($subject) !== null;
    }

    /**
     * Returns the class name of the resolver that would handle the given
     * subject, without instantiating it or calling resolve(). Useful for
     * logging, telemetry, or test assertions about *which* resolver would
     * fire, without triggering resolution itself. Also the single search
     * routine match() builds on.
     */
    public function resolverClassFor(mixed $subject): ?string
    {
        $this->sortedKeys ??= $this->computeSortedKeys();

        foreach ($this->sortedKeys as $class) {
            if ($class::supports($subject)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Resolves via the first matching resolver.
     *
     * Throws ResolverNotFoundException if no registered resolver matches.
     * A matched resolver's own resolve() may separately throw
     * SubjectDeclinedException if it declines after closer inspection.
     * Neither is caught here — both propagate straight to the caller,
     * including through any nested ResolverDispatcher a resolver may
     * delegate to internally.
     */
    public function resolve(mixed $subject): mixed
    {
        $resolver = $this->match($subject);

        if ($resolver === null) {
            throw ResolverNotFoundException::forSubject($subject);
        }

        return $resolver->resolve($subject);
    }

    public function reset(): void
    {
        $this->resolvers = [];
        $this->sortedKeys = null;
        $this->registrationCounter = 0;
    }

    private function match(mixed $subject): ?ResolverInterface
    {
        $class = $this->resolverClassFor($subject);

        if ($class === null) {
            return null;
        }

        return $this->resolvers[$class]['instance'] ??= ($this->resolvers[$class]['factory'])();
    }

    private function computeSortedKeys(): array
    {
        $keys = array_keys($this->resolvers);
        usort(
            $keys,
            fn($a, $b) => $this->resolvers[$b]['priority'] <=> $this->resolvers[$a]['priority']
                ?: $this->resolvers[$b]['order'] <=> $this->resolvers[$a]['order'],
        );
        return $keys;
    }
}
