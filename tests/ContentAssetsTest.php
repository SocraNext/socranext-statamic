<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Content\{AssetImporter, IdentityMap};
use SocraNext\Statamic\Support\StateStore;
use Statamic\Facades\{Asset, AssetContainer};
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContentAssetsTest extends TestCase
{
    public function test_native_asset_upload_keeps_binary_and_accessibility_metadata_and_reuses_content(): void
    {
        config(['filesystems.disks.socranext_test' => ['driver' => 'local', 'root' => $this->temporaryDirectory.'/assets', 'url' => 'https://example.com/assets']]);
        AssetContainer::make('socranext')->disk('socranext_test')->save();
        $importer = new class extends AssetImporter {
            protected function download(string $url): array
            {
                $file = tempnam(sys_get_temp_dir(), 'socranext-asset-test-');
                $image = imagecreatetruecolor(2, 2);
                imagepng($image, $file);
                return [$file, 'image/png'];
            }
        };
        $id = $importer->import(['featuredImageUrl' => 'https://images.example.com/photo.png', 'featuredImageAlt' => 'Useful alt', 'featuredImageTitle' => 'Image title']);
        $asset = Asset::find($id);
        $this->assertNotNull($asset);
        $this->assertSame('Useful alt', $asset->get('alt'));
        $this->assertTrue($asset->exists());
        $this->assertSame('image/png', $asset->mimeType());
        $this->assertSame($id, $importer->import(['featuredImageUrl' => 'https://images.example.com/photo.png', 'featuredImageAlt' => 'Changed alt']));
        $this->assertSame('Useful alt', Asset::find($id)->get('alt'));
    }

    public function test_asset_downloader_rejects_private_and_ambiguous_urls_before_http(): void
    {
        $importer = new class extends AssetImporter { public function fetch(string $url): array { return $this->download($url); } };
        foreach (['http://example.com/image.png', 'https://127.0.0.1/image.png', 'https://[::1]/image.png', 'https://169.254.169.254/image.png', 'https://100.100.100.200/image.png', 'https://user:pass@example.com/image.png', 'https://example.com:8443/image.png', 'https://[::ffff:127.0.0.1]/image.png'] as $url) {
            try { $importer->fetch($url); $this->fail('Private or unsupported image URL was accepted.'); }
            catch (HttpException $error) { $this->assertSame(422, $error->getStatusCode()); }
        }
    }

    public function test_identity_collision_fails_closed_instead_of_assigning_wrong_content(): void
    {
        $map = app(IdentityMap::class);
        $id = $map->id('entry', 'native-a', 'default');
        app(StateStore::class)->transaction(function (array &$data) use ($id) { $data['identities']['records'][$id]['native_id'] = 'different-native'; });
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('collision');
        $map->id('entry', 'native-a', 'default');
    }
}
