<?php

namespace Tests\Unit;

use App\Legacy\LegacyPathResolver;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LegacyPathResolverTest extends TestCase
{
    private string $legacyRoot;

    private string $outsideFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->legacyRoot = storage_path('framework/testing/legacy-path-'.uniqid());
        File::ensureDirectoryExists($this->legacyRoot.'/users/member/image');
        File::put($this->legacyRoot.'/users/member/image/sample.jpg', 'fixture');
        $this->outsideFile = $this->legacyRoot.'-outside';
        File::put($this->outsideFile, 'fixture');
        config(['legacy.files_root' => $this->legacyRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->legacyRoot);
        File::delete($this->outsideFile);
        parent::tearDown();
    }

    public function test_it_resolves_database_paths_from_the_legacy_application_root(): void
    {
        $resolver = app(LegacyPathResolver::class);

        $relative = $resolver->resolve('users/member/image/sample.jpg');
        $oldRoute = $resolver->resolve('/wk/users/member/image/sample.jpg');

        $this->assertSame('present', $relative['status']);
        $this->assertSame($this->legacyRoot.'/users/member/image/sample.jpg', $relative['path']);
        $this->assertSame('present', $oldRoute['status']);
    }

    public function test_it_rejects_external_urls_and_paths_outside_the_root(): void
    {
        $resolver = app(LegacyPathResolver::class);

        $this->assertSame('unsupported', $resolver->resolve('https://example.com/image.jpg')['status']);
        $this->assertSame('outside', $resolver->resolve('../'.basename($this->outsideFile))['status']);
    }
}
