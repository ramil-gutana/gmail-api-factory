<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BumpVersionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/gmail-bump-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/VERSION', "1.2.3\n");
        file_put_contents($this->dir.'/CHANGELOG.md', "# Changelog\n\n## [Unreleased]\n\n### Fixed\n\n- A bug.\n\n## [1.2.3] - 2026-01-01\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);
    }

    private function bump(string ...$args): array
    {
        $script = escapeshellarg(dirname(__DIR__, 2).'/bin/bump-version');
        $args = array_map('escapeshellarg', [...$args, '--dir='.$this->dir, '--date=2026-10-31']);
        exec(PHP_BINARY." $script ".implode(' ', $args).' 2>&1', $output, $code);

        return [$code, implode("\n", $output)];
    }

    public static function bumpTypes(): array
    {
        return [
            'patch' => ['patch', '1.2.4'],
            'minor' => ['minor', '1.3.0'],
            'major' => ['major', '2.0.0'],
            'explicit' => ['1.5.0', '1.5.0'],
        ];
    }

    #[DataProvider('bumpTypes')]
    public function test_it_bumps_the_version_file(string $type, string $expected): void
    {
        [$code, $output] = $this->bump($type);

        $this->assertSame(0, $code, $output);
        $this->assertSame("1.2.3 -> $expected", $output);
        $this->assertSame($expected, trim(file_get_contents($this->dir.'/VERSION')));
    }

    public function test_it_releases_the_unreleased_changelog_section(): void
    {
        $this->bump('minor');

        $this->assertSame(
            "# Changelog\n\n## [Unreleased]\n\n## [1.3.0] - 2026-10-31\n\n### Fixed\n\n- A bug.\n\n## [1.2.3] - 2026-01-01\n",
            file_get_contents($this->dir.'/CHANGELOG.md')
        );
    }

    public function test_dry_run_changes_nothing(): void
    {
        [$code, $output] = $this->bump('patch', '--dry-run');

        $this->assertSame(0, $code);
        $this->assertSame('1.2.3 -> 1.2.4 (dry run)', $output);
        $this->assertSame("1.2.3\n", file_get_contents($this->dir.'/VERSION'));
    }

    public function test_it_rejects_invalid_or_lower_versions(): void
    {
        [$code, $output] = $this->bump('banana');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Unknown bump type', $output);

        [$code, $output] = $this->bump('1.0.0');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('must be greater than', $output);

        $this->assertSame("1.2.3\n", file_get_contents($this->dir.'/VERSION'));
    }
}
