# digibit/resolver

Priority-ordered, lazily-constructed resolver registry.

`ResolverDispatcher` holds a set of resolver classes and, given a subject
value, finds the first one that claims to support it and delegates to it.
Resolver instances are only constructed the moment they're actually needed.

`ResolverDispatcher` is `final` and not meant to be extended — it's a
container for resolvers, not a resolver itself. Consumers hold it as a
private dependency and expose their own domain-named method on top of it
(`format()`, `snapshot()`, etc.) rather than subclassing.

## Installation

    composer require digibit/resolver

Requires PHP ^8.1.

## Core concepts

### `ResolverInterface`

Every resolver implements two methods:

```php
interface ResolverInterface
{
    public static function supports(mixed $subject): bool;

    public function resolve(mixed $subject): mixed;
}
```

- `supports()` is **static** so the dispatcher can probe applicability
  without paying the cost of instantiation. It may be called once per
  registered resolver on every `resolve()`/`supports()`/`resolverClassFor()`
  call until a match is found, so keep it cheap.
- `resolve()` is called on the (lazily-constructed) instance of the first
  matching resolver.
- A resolver may throw `SubjectDeclinedException` from `resolve()` to
  decline a subject after inspecting it more closely than `supports()`
  could. `ResolverDispatcher` does not catch this exception or fall
  through to the next resolver — it propagates straight to the caller.
  Declining after matching is between a resolver and its caller, not
  something the dispatcher mediates.

### `ResolverDispatcher`

```php
use Digibit\Resolver\ResolverDispatcher;

$dispatcher = new ResolverDispatcher();

$dispatcher->register(InvoiceResolver::class, priority: 10);
$dispatcher->register(FallbackResolver::class);

$result = $dispatcher->resolve($subject);
```

- `register(string $resolverClass, int $priority = 0)` — registers a
  resolver class, instantiated via `new $resolverClass()` when first
  matched.
- `registerFactory(string $producedClassName, \Closure $factory, int $priority = 0)` —
  same as `register()`, but lets you supply a custom factory closure (e.g.
  for resolvers with constructor dependencies). `$producedClassName` must
  implement `ResolverInterface`, or a `\LogicException` is thrown.
  Re-registering the same class replaces its previous entry (factory and
  priority) rather than adding a duplicate.
- `unregister(string $resolverClass)` — removes a registered resolver, if
  present. Safe to call whether or not it's registered.
- `has(string $resolverClass): bool` — whether a resolver class is
  currently registered (regardless of whether it would match anything).
- `getPriority(string $resolverClass): ?int` — the priority a registered
  resolver was registered with, or `null` if it isn't registered. Useful
  for replacing a resolver's factory while preserving its existing
  priority:

  ```php
  $priority = $dispatcher->getPriority(AuditedEntityResolver::class) ?? 0;
  $dispatcher->registerFactory(AuditedEntityResolver::class, $newFactory, $priority);
  ```

- `supports(mixed $subject): bool` — true if any registered resolver
  claims the subject.
- `resolverClassFor(mixed $subject): ?string` — the class name of the
  resolver that would handle the given subject, without instantiating it
  or calling `resolve()`. Useful for logging, telemetry, or test
  assertions about *which* resolver would fire.
- `resolve(mixed $subject): mixed` — resolves via the first matching
  resolver. Throws `ResolverNotFoundException` if none match. Callers
  that want a non-throwing check first should call `supports()`.
- `reset(): void` — clears all registered resolvers.

### Ordering

Resolvers are tried highest-priority first. Ties are broken by
registration order, most-recently-registered first — so a later
`register()` call with an equal priority takes precedence over an earlier
one. The sorted order is computed lazily and cached until the next
`register()`/`registerFactory()`/`unregister()` call invalidates or
adjusts it.

### Register handlers and use

```php
$formatter = new MoneyFormatter();
$formatter->register(UsdMoneyFormatter::class);
$formatter->register(EurMoneyFormatter::class);

echo $formatter->format(new UsdMoney(19.99)); // '$19.99'
```

For handlers that need dependencies injected, use `registerFactory()`:

```php
$formatter->registerFactory(
    UsdMoneyFormatter::class,
    fn() => new UsdMoneyFormatter($currencyService),
);
```

This is also how to wire resolvers through a PSR-11 (or any other)
container — no special-cased method needed, just pass a closure that
calls into it:

```php
$dispatcher->registerFactory(
    AuditedEntityResolver::class,
    fn() => $container->get(AuditedEntityResolver::class),
);
```

### `DefaultResolver`

A catch-all resolver whose `supports()` always returns `true`. Register it
at the lowest priority to guarantee it only runs as a last resort —
anything registered after it at an equal or lower priority would
otherwise be permanently shadowed, since its `supports()` always matches:

```php
use Digibit\Resolver\DefaultResolver;

$dispatcher->registerFactory(
    DefaultResolver::class,
    fn() => new DefaultResolver(fn($subject) => /* fallback handling */ null),
    priority: PHP_INT_MIN,
);
```

Registering a `DefaultResolver` means `resolve()` never throws
`ResolverNotFoundException` — every subject falls through to it instead.
Whether that's desirable depends on whether "unhandled" should be a silent
default or a caught bug in your domain.

### Exceptions

Two distinct failure modes, both extending the shared `ResolverException`
so a caller who doesn't need the distinction can catch broadly:

- **`ResolverNotFoundException`** — thrown by `ResolverDispatcher::resolve()`
  when no registered resolver's `supports()` claims the subject at all.
- **`SubjectDeclinedException`** — never thrown by the dispatcher itself.
  Available for a resolver to throw from its own `resolve()` when, having
  matched via `supports()`, closer inspection of the actual subject
  reveals it can't be handled after all.

```php
use Digibit\Resolver\ResolverException;
use Digibit\Resolver\ResolverNotFoundException;
use Digibit\Resolver\SubjectDeclinedException;

try {
    $result = $dispatcher->resolve($subject);
} catch (ResolverNotFoundException $e) {
    // nothing was registered for this at all
} catch (SubjectDeclinedException $e) {
    // something matched, then declined on closer inspection
} catch (ResolverException $e) {
    // either, if the distinction doesn't matter here
}
```

Nothing in the dispatcher catches either exception, and it never retries
another resolver after one is thrown. This matters most when nesting: if
a resolver's own `resolve()` delegates to another `ResolverDispatcher`
internally, an unhandled subject at the inner dispatcher throws the same
way, and nothing at any level catches it on the way up — it bubbles all
the way to whoever called the outermost `resolve()`, indistinguishable
from the outer dispatcher having thrown it directly. If you need to know
which dispatcher actually failed to match, catch it at the point of
nesting and rethrow with added context, or check `supports()` before
delegating.
