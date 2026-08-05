<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\EarlyReturn\Rector\If_\ChangeOrIfContinueToMultiContinueRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\ValueObject\PhpVersion;

/**
 * Rector for the bundle's own code.
 *
 * Not to be confused with `rector/sets/`, which is what an application imports to upgrade *from* an
 * older version of this bundle. This file is the other direction: keeping this codebase idiomatic
 * for the PHP it claims to need.
 *
 * Run it before opening a pull request:
 *
 *     bin/rector process --dry-run    # read the diff first
 *     bin/rector process
 *
 * It is expected to find nothing on a clean checkout. When it does find something, that is the
 * point — read the diff rather than applying it blind, because the rules below are chosen for a
 * library and a library has callers.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/config',
        // rector/sets is deliberately left out. Those files are consumer-facing configuration, read
        // by people deciding whether to trust them, and the comments in them carry more than the
        // code does. Nothing should tidy them but a person.
    ])
    // From composer.json, which asks for ^8.4. Pinned rather than detected so that running this on
    // a newer PHP does not quietly rewrite the code into something the minimum cannot parse
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSkip([
        // Both analysers already run at their strictest here, so these sets are a second opinion
        // rather than a source of truth. What follows is where that second opinion is wrong for
        // this project.

        // The constructors of the listeners and managers take arguments the container fills and
        // some that exist only so an application can pass something else. An argument that nothing
        // inside the bundle reads is not an unused one; removing it breaks every caller.
        RemoveUnusedPromotedPropertyRector::class,

        // `declare(strict_types=1)` is not a style choice and does not belong in a tooling run. It
        // changes how every call this bundle makes coerces its arguments, which is a behavioural
        // change across 103 files, and it wants its own decision, its own commit and a release to
        // land in. Turn this off deliberately if that is the decision; do not let Rector make it.
        SafeDeclareStrictTypesRector::class,

        // PHP 8.4 lets `new Foo()->bar()` drop the wrapping parentheses. The bundle requires 8.4,
        // so this would parse — but it rewrites fifty files to prevent no bug and improve no type,
        // and the older spelling is the one every reader already knows. Delete this line if the
        // house style is to be the newest syntax available.
        NewMethodCallWithoutParenthesesRector::class,

        // It rewrites `@return T` on a generic helper into the one class it happens to see, which
        // is what delegateOrFail() has a `@template` for: the whole point is that the return type
        // follows the interface asked for. Narrowing it there loses the callers' type safety.
        NarrowObjectReturnTypeRector::class,

        // `null !== $token` becomes `$token instanceof Some\Fully\Qualified\Name`, which is longer,
        // drags a FQN into the middle of a condition, and says less than the null check it replaces
        // — the question really being asked is whether there is one.
        FlipTypeControlToUseExclusiveTypeRector::class,

        // The private static helpers are static on purpose. `self::hash()` and `self::key()` touch
        // no state and are marked `@psalm-pure`, which is exactly what being static asserts; turning
        // them into `$this->` calls throws that away for no gain.
        LocallyCalledStaticMethodToNonStaticRector::class,

        // `if ($a || $b || $c) { continue; }` becomes three ifs and three continues. Each guard is
        // one thought — "this class is not one to create a collection for" — and splitting it into
        // three makes the reader reassemble what the condition already said.
        ChangeOrIfContinueToMultiContinueRector::class,

        // The cookie defaults are merged in the constructor body on purpose: the array arrives
        // partial from configuration and the merge is what fills it in.
        InlineConstructorDefaultToPropertyRector::class => [
            __DIR__.'/src/EventListener/AttachRefreshTokenOnSuccessListener.php',
        ],

        // Fixtures exist to be odd. A token class without an identifier, a repository implementing
        // only the base interface — each one is a shape the bundle has to cope with, and "tidying"
        // them removes the thing they were written to prove.
        __DIR__.'/tests/Functional/Fixtures',
    ]);
