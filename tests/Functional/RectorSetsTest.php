<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rector\Config\RectorConfig;

/**
 * The rule sets are shipped, so they are part of the package and can rot like anything else.
 *
 * The failure worth guarding against is a typo in a rename target: nothing here would notice, and an
 * application running the set would be sent to a class that does not exist by the very tool it ran
 * to avoid that.
 */
final class RectorSetsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function sets(): iterable
    {
        yield '1.5 to 2.0' => ['gesdinet-jwt-refresh-token-20.php'];
        yield '2.0 to 2.1' => ['gesdinet-jwt-refresh-token-21.php'];
        yield '2.1 to 2.2' => ['gesdinet-jwt-refresh-token-22.php'];
        yield '2.2 to 3.0' => ['gesdinet-jwt-refresh-token-30.php'];
        yield 'every hop' => ['gesdinet-jwt-refresh-token-all.php'];
    }

    #[DataProvider('sets')]
    public function test_the_set_is_where_the_documentation_says_it_is(string $set): void
    {
        $this->assertFileExists(self::path($set), 'UPGRADE-RECTOR.md tells people to import this path');
    }

    #[DataProvider('sets')]
    public function test_the_set_loads(string $set): void
    {
        $configure = require self::path($set);

        $this->assertIsCallable($configure);

        // Loading it is what proves the file is a set rather than merely valid PHP
        $configure(new RectorConfig());
    }

    /**
     * A class a rename points at has to exist, or the set sends an application from one missing class
     * to another.
     *
     * The shipped file is read rather than executed. A set is a closure that configures Rector's own
     * container, and standing something in for that to record the calls is more machinery than this
     * is worth — what needs checking is the pairs, and those are right there in the source.
     */
    public function test_every_class_rename_points_at_a_class_that_exists(): void
    {
        $renames = self::pairsIn('gesdinet-jwt-refresh-token-20.php', 'Gesdinet\\\\');

        $this->assertNotSame([], $renames, 'The 2.0 set is the one with class renames in it');

        foreach ($renames as [$old, $new]) {
            $this->assertTrue(class_exists($new), sprintf('"%s" is renamed to "%s", which does not exist', $old, $new));
        }
    }

    /**
     * Ids of services that survived have to land on the prefix the bundle actually uses. The ones
     * that did not survive are renamed onto ids that deliberately do not exist, and they share the
     * prefix so that they read as this bundle's when one turns up missing.
     */
    public function test_every_container_id_rename_lands_on_the_bundle_prefix(): void
    {
        $renames = self::pairsIn('gesdinet-jwt-refresh-token-20.php', 'gesdinet\.');

        $this->assertNotSame([], $renames);

        foreach ($renames as [$old, $new]) {
            $this->assertStringStartsWith('gesdinet.jwtrefreshtoken', $old, 'Only the old spelling is renamed');
            $this->assertStringStartsWith('gesdinet_jwt_refresh_token.', $new);
        }
    }

    /**
     * Every `'old' => 'new'` pair in a set whose left side starts with the given pattern.
     *
     * @return list<array{string, string}>
     */
    private static function pairsIn(string $set, string $leftPattern): array
    {
        $source = (string) file_get_contents(self::path($set));

        preg_match_all(
            sprintf("/'(%s[^']+)'\s*=>\s*'([^']+)'/", $leftPattern),
            $source,
            $matches,
            \PREG_SET_ORDER
        );

        return array_map(
            static fn (array $match): array => [$match[1], $match[2]],
            $matches
        );
    }

    private static function path(string $set): string
    {
        return \dirname(__DIR__, 2).'/rector/sets/'.$set;
    }
}
