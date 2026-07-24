<?php declare(strict_types=1);

namespace Digibit\Resolver\Tests;

use Digibit\Resolver\DefaultResolver;
use Digibit\Resolver\ResolverDispatcher;
use Digibit\Resolver\ResolverNotFoundException;
use Digibit\Resolver\SubjectDeclinedException;
use Digibit\Resolver\Tests\Fixtures\DateTimeImmutableResolver;
use Digibit\Resolver\Tests\Fixtures\DateTimeInterfaceResolver;
use Digibit\Resolver\Tests\Fixtures\DecliningResolver;
use Digibit\Resolver\Tests\Fixtures\NeverMatchesResolver;
use Digibit\Resolver\Tests\Fixtures\NotAResolver;
use PHPUnit\Framework\TestCase;

final class ResolverDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        DateTimeInterfaceResolver::reset();
        DateTimeImmutableResolver::reset();
        NeverMatchesResolver::reset();
    }

    public function test_unregistered_subject_does_not_match(): void
    {
        $dispatcher = new ResolverDispatcher();

        self::assertFalse($dispatcher->supports(new \DateTimeImmutable('2026-01-01')));
        self::assertNull($dispatcher->resolverClassFor(new \DateTimeImmutable('2026-01-01')));
    }

    public function test_resolve_throws_when_no_resolver_matches(): void
    {
        $dispatcher = new ResolverDispatcher();

        $this->expectException(ResolverNotFoundException::class);
        $this->expectExceptionMessage('No resolver registered for subject of type');

        $dispatcher->resolve(new \DateTimeImmutable('2026-01-01'));
    }

    public function test_registered_resolver_matches_and_resolves(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class);

        self::assertTrue($dispatcher->supports(new \DateTimeImmutable('2026-01-01')));
        self::assertSame(
            'interface:2026-01-01',
            $dispatcher->resolve(new \DateTimeImmutable('2026-01-01')),
        );
    }

    public function test_higher_priority_resolver_wins_over_lower_priority(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class, priority: 0);
        $dispatcher->register(DateTimeImmutableResolver::class, priority: 10);

        self::assertSame(
            'immutable:2026-01-01',
            $dispatcher->resolve(new \DateTimeImmutable('2026-01-01')),
        );
    }

    public function test_registration_order_breaks_ties_when_priorities_are_equal(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class); // registered first, same priority
        $dispatcher->register(DateTimeImmutableResolver::class); // registered last — wins on tie

        self::assertSame(
            'immutable:2026-01-01',
            $dispatcher->resolve(new \DateTimeImmutable('2026-01-01')),
        );
    }

    public function test_resolver_class_for_reports_the_match_without_instantiating_or_resolving(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class);

        self::assertSame(
            DateTimeInterfaceResolver::class,
            $dispatcher->resolverClassFor(new \DateTimeImmutable('2026-01-01')),
        );
        self::assertSame(0, DateTimeInterfaceResolver::$constructions);
    }

    public function test_resolvers_are_constructed_lazily_only_on_match(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(NeverMatchesResolver::class);
        $dispatcher->register(DateTimeInterfaceResolver::class);

        self::assertSame(0, NeverMatchesResolver::$constructions);
        self::assertSame(0, DateTimeInterfaceResolver::$constructions);

        $dispatcher->resolve(new \DateTimeImmutable('2026-01-01'));

        self::assertSame(0, NeverMatchesResolver::$constructions, 'a non-matching resolver must never be constructed');
        self::assertSame(1, DateTimeInterfaceResolver::$constructions);
    }

    public function test_matched_resolver_instance_is_cached_across_calls(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class);

        $dispatcher->resolve(new \DateTimeImmutable('2026-01-01'));
        $dispatcher->resolve(new \DateTimeImmutable('2026-06-01'));

        self::assertSame(1, DateTimeInterfaceResolver::$constructions, 'the same resolver instance should be reused, not rebuilt per call');
    }

    public function test_register_factory_uses_the_supplied_closure_instead_of_a_bare_constructor(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->registerFactory(
            DateTimeInterfaceResolver::class,
            static fn() => new DateTimeInterfaceResolver(),
        );

        self::assertSame(
            'interface:2026-01-01',
            $dispatcher->resolve(new \DateTimeImmutable('2026-01-01')),
        );
    }

    public function test_registering_a_class_that_does_not_implement_resolver_interface_throws(): void
    {
        $dispatcher = new ResolverDispatcher();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(NotAResolver::class . ' must implement');

        $dispatcher->register(NotAResolver::class);
    }

    public function test_registering_the_same_class_twice_replaces_the_first_registration(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class, priority: 0);
        $dispatcher->register(DateTimeInterfaceResolver::class, priority: 5);

        self::assertSame(5, $dispatcher->getPriority(DateTimeInterfaceResolver::class));
    }

    public function test_has_reflects_registration_state(): void
    {
        $dispatcher = new ResolverDispatcher();

        self::assertFalse($dispatcher->has(DateTimeInterfaceResolver::class));

        $dispatcher->register(DateTimeInterfaceResolver::class);

        self::assertTrue($dispatcher->has(DateTimeInterfaceResolver::class));
    }

    public function test_get_priority_returns_null_for_unregistered_class(): void
    {
        $dispatcher = new ResolverDispatcher();

        self::assertNull($dispatcher->getPriority(DateTimeInterfaceResolver::class));
    }

    public function test_get_priority_returns_the_registered_priority(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class, priority: 7);

        self::assertSame(7, $dispatcher->getPriority(DateTimeInterfaceResolver::class));
    }

    public function test_unregister_removes_a_resolver(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class);

        $dispatcher->unregister(DateTimeInterfaceResolver::class);

        self::assertFalse($dispatcher->has(DateTimeInterfaceResolver::class));
        self::assertFalse($dispatcher->supports(new \DateTimeImmutable('2026-01-01')));
    }

    public function test_unregister_of_a_class_that_was_never_registered_is_a_no_op(): void
    {
        $dispatcher = new ResolverDispatcher();

        $dispatcher->unregister(DateTimeInterfaceResolver::class);

        self::assertFalse($dispatcher->has(DateTimeInterfaceResolver::class));
    }

    public function test_unregister_after_sorted_keys_have_been_cached_still_takes_effect(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class, priority: 0);
        $dispatcher->register(DateTimeImmutableResolver::class, priority: 10);

        // force the sorted-keys cache to populate before unregistering
        $dispatcher->resolve(new \DateTimeImmutable('2026-01-01'));

        $dispatcher->unregister(DateTimeImmutableResolver::class);

        self::assertSame(
            'interface:2026-01-01',
            $dispatcher->resolve(new \DateTimeImmutable('2026-01-01')),
        );
    }

    public function test_reset_clears_all_registrations(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class);
        self::assertTrue($dispatcher->supports(new \DateTimeImmutable('2026-01-01')));

        $dispatcher->reset();

        self::assertFalse($dispatcher->supports(new \DateTimeImmutable('2026-01-01')));
        self::assertNull($dispatcher->getPriority(DateTimeInterfaceResolver::class));
    }

    public function test_default_resolver_matches_anything_and_acts_as_a_fallback(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DateTimeInterfaceResolver::class, priority: 10);
        $dispatcher->registerFactory(
            DefaultResolver::class,
            static fn() => new DefaultResolver(fn($subject) => 'fallback:' . get_debug_type($subject)),
            priority: \PHP_INT_MIN,
        );

        self::assertSame(
            'interface:2026-01-01',
            $dispatcher->resolve(new \DateTimeImmutable('2026-01-01')),
        );
        self::assertSame(
            'fallback:string',
            $dispatcher->resolve('unhandled subject'),
        );
    }

    public function test_subject_declined_exception_from_a_matched_resolver_propagates_uncaught(): void
    {
        $dispatcher = new ResolverDispatcher();
        $dispatcher->register(DecliningResolver::class);

        $this->expectException(SubjectDeclinedException::class);
        $this->expectExceptionMessage(DecliningResolver::class . ' matched via supports() but declined');

        $dispatcher->resolve('anything');
    }
}
