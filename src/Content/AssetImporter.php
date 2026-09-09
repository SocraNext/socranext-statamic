<?php

namespace SocraNext\Statamic\Content;

use Illuminate\Http\UploadedFile;
use Statamic\Facades\{Asset, AssetContainer};

class AssetImporter
{
    public function import(array $payload): ?string
    {
        $url = $payload['featuredImageUrl'] ?? null;
        if (!$url) return null;
        $container = AssetContainer::find(config('socranext.content.asset_container', 'socranext'));
        abort_unless($container, 422, 'Configure an asset container before publishing an image.');
        [$file, $mime] = $this->download($url);
        try {
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'][$mime];
            $name = hash_file('sha256', $file).'.'.$extension;
            $id = $container->handle().'::socranext/'.$name;
            $asset = Asset::find($id);
            if (!$asset) {
                $asset = Asset::make()->container($container)->path('socranext/'.$name);
                abort_unless($asset->upload(new UploadedFile($file, $name, $mime, null, true)), 422, 'Asset upload was rejected.');
                $asset->set('socranext_owned', true);
            }
            // Shared content-addressed images retain existing customer metadata.
            if (!$asset->get('alt') && isset($payload['featuredImageAlt'])) $asset->set('alt', $payload['featuredImageAlt']);
            if (!$asset->get('title') && isset($payload['featuredImageTitle'])) $asset->set('title', $payload['featuredImageTitle']);
            abort_unless($asset->save(), 422, 'Asset save was rejected.');
            return $asset->id();
        } finally {
            @unlink($file);
        }
    }

    /** Resolve once and pin the public address; redirects are deliberately rejected. */
    protected function download(string $url): array
    {
        $parts = parse_url($url);
        abort_unless(is_array($parts) && ($parts['scheme'] ?? '') === 'https' && isset($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass'])
            && ($parts['port'] ?? 443) === 443 && !preg_match('/[\x00-\x20\\\\]/', $url), 422, 'Image URL must be public HTTPS.');
        $host = strtolower(trim($parts['host'], '[]'));
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : collect(dns_get_record($host, DNS_A | DNS_AAAA) ?: [])
            ->map(fn ($record) => $record['ip'] ?? $record['ipv6'] ?? null)->filter()->values()->all();
        abort_unless($addresses, 422, 'Image hostname does not resolve.');
        foreach ($addresses as $address) abort_unless($this->publicAddress($address), 422, 'Image hostname must resolve only to public addresses.');
        $file = tempnam(sys_get_temp_dir(), 'socranext-image-');
        $stream = fopen($file, 'wb');
        $bytes = 0;
        $max = max(1, min(50 * 1024 * 1024, (int) config('socranext.content.asset_max_bytes', 10 * 1024 * 1024)));
        $curl = curl_init($url);
        $ip = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
        curl_setopt_array($curl, [CURLOPT_RESOLVE => [$host.':443:'.$ip], CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROXY => '',
            CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use ($stream, &$bytes, $max) {
                $bytes += strlen($chunk);
                return $bytes > $max ? 0 : fwrite($stream, $chunk);
            }]);
        try {
            $ok = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } finally {
            fclose($stream);
            curl_close($curl);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        $size = @getimagesize($file);
        if (!$ok || $status !== 200 || !$bytes || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true)
            || !$size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 40000000) {
            @unlink($file);
            abort(422, 'Image download failed, exceeded the size limit, or has an unsupported MIME type.');
        }
        return [$file, $mime];
    }

    protected function publicAddress(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        // Exclude transition/mapped forms which can tunnel a private IPv4 address.
        $packed = inet_pton($ip);
        if (strlen($packed) === 16) return (ord($packed[0]) & 0xe0) === 0x20
            && substr($packed, 0, 2) !== "\x20\x02" && substr($packed, 0, 4) !== "\x20\x01\x00\x00";
        $first = ord($packed[0]);
        return !($first === 100 && ord($packed[1]) >= 64 && ord($packed[1]) <= 127)
            && !($first === 192 && ord($packed[1]) === 0 && in_array(ord($packed[2]), [0, 2], true))
            && !($first === 192 && ord($packed[1]) === 88 && ord($packed[2]) === 99) && $first < 224;
    }
}
