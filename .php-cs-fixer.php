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

/**
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
        // @Symfony separates the class imports from the function ones with a blank line; the hosted
        // check does not. Matched to the hosted one, since that is what gates a pull request.
        'blank_line_between_import_groups' => false,

        // A comment sitting before a `} elseif` belongs to the branch above it, not to the chain.
        // @Symfony's default sticks it to the outer indentation and the hosted check indents it
        // with the block, which is where the @codeCoverageIgnore comments in the extension read
        // right. Matched to the hosted one.
        'statement_indentation' => ['stick_comment_to_next_continuous_control_statement' => false],

        // An anonymous class gets its parentheses. @Symfony leaves them off and the hosted check
        // puts them on, and a disagreement here is two tools editing the same line forever.
        'new_with_parentheses' => ['anonymous_class' => true, 'named_class' => true],
        'phpdoc_separation' => true,
        'no_unused_imports' => true,

        // The docblocks here carry types neither PHP nor @Symfony's idea of "superfluous" knows
        // about: positive-int, class-string<T>, list<Session>, array shapes. phpstan runs at level
        // 10 and psalm at its strictest on the strength of them, so nothing may strip them.
        'no_superfluous_phpdoc_tags' => false,
        'phpdoc_to_comment' => false,

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
