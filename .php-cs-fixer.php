<?php

declare(strict_types=1);

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Formatting for the bundle.
 *
 * Kept deliberately close to `rector.php`, because the two tools overlap and an overlap left
 * unmanaged means every run of one undoes part of the last run of the other. Where they touch the
 * same thing, the rule below says which of them owns it.
 *
 *     bin/php-cs-fixer fix --dry-run --diff    # read it first
 *     bin/php-cs-fixer fix
 *
 * `.styleci.yml` mirrors this so the hosted check agrees with the local one. If you change a rule
 * here, change it there.
 */
$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config', __DIR__.'/rector'])
    // The config files themselves, so the tooling holds to the same style as the code it formats
    ->append([__FILE__, __DIR__.'/rector.php']);

return new PhpCsFixer\Config()
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // ---- Where this and Rector touch the same code -------------------------------------

        // This tool owns the declare, and Rector's SafeDeclareStrictTypesRector is switched off
        // for it. Rector adds it only where it can prove coercion is not being relied on, which
        // left 57 of 111 files with it and the rest without — a split no reader would guess at.
        // One owner, applied everywhere, and the suite is what says it was safe.
        'declare_strict_types' => true,
        // Rector eats the blank line between the licence header and the namespace on its way past.
        // This puts it back, so running one then the other settles instead of oscillating.
        'blank_lines_before_namespace' => true,

        // Rector leaves conditions in whichever order it built them; the codebase is Yoda
        // throughout and this is what keeps it that way without anyone hand-fixing the output.
        'yoda_style' => true,

        // Rector does not import names — withImportNames() is deliberately off — so neither does
        // this. With both off, an inline \DateTimeInterface stays inline and nothing fights.
        'fully_qualified_strict_types' => false,
        'global_namespace_import' => false,

        // ---- Rules this project wants, or refuses ------------------------------------------

        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        // Three rules where the hosted check deviates from @Symfony: it drops the blank line
        // between the class and function import groups, puts parentheses on anonymous classes, and
        // indents a comment before a `} elseif` with the block rather than the chain. Symfony's
        // convention wins, so none of them is overridden here and .styleci.yml brings the hosted
        // check into line instead.
        'phpdoc_separation' => true,
        'no_unused_imports' => true,

        // The docblocks here carry types neither PHP nor @Symfony's idea of "superfluous" knows
        // about: positive-int, class-string<T>, list<Session>, array shapes. phpstan runs at level
        // 10 and psalm at its strictest on the strength of them, so nothing may strip them.
        'no_superfluous_phpdoc_tags' => false,

        // This one is @Symfony's own — allow_before_return_statement is false there too, so a
        // comment above `return RectorConfig::configure()` is a comment. Only the ignored tags are
        // added: an inline `/** @var Foo&MockObject $x */` is the only thing telling phpstan what a
        // mock is, and turning those into comments would take its knowledge of the suite away.
        'phpdoc_to_comment' => [
            'allow_before_return_statement' => false,
            'ignored_tags' => ['var', 'phpstan-var', 'psalm-var', 'template', 'psalm-suppress', 'phpstan-ignore'],
        ],

        // Not part of @Symfony; it arrives with the risky set and turns those file comments back
        // into docblocks, so the two rules were taking turns on every run.
        'comment_to_phpdoc' => false,

        // `/** @var Foo&MockObject $x */` in the tests is what tells the analysers what a mock is.
        // Turning those into plain comments would take phpstan's knowledge of the test suite away.
        'phpdoc_var_annotation_correct_order' => true,

        // The test names are sentences — test_reads_back_a_token_it_stored — and are meant to be
        // read as the failure message. Camel-casing them turns the suite's documentation back into
        // identifiers.
        'php_unit_method_casing' => false,

        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,

        // Both spellings appear across the codebase and neither is wrong; picking one would be a
        // large diff that prevents nothing. Left alone deliberately rather than by omission.
        'native_function_invocation' => false,
    ]);
