<?php

namespace SocraNext\Statamic\Rendering;

use Statamic\Facades\Site;

/** Isolate Statamic's per-site paths and namespace hints from the active request. */
class SiteViewFinder
{
    public static function forSite(string $site)
    {
        $finder = clone view()->getFinder();
        $finder->flush();
        $finder->setPaths(self::paths($finder->getPaths(), $site));
        foreach ($finder->getHints() as $namespace => $paths) $finder->replaceNamespace($namespace, self::paths($paths, $site));
        return $finder;
    }

    private static function paths(array $paths, string $site): array
    {
        $paths = array_values(array_unique(array_map(fn ($path) => rtrim($path, '/'), $paths)));
        $sites = Site::all()->map(fn ($site) => $site->handle())->all();
        // AddViewPaths may already have expanded the current request's finder.
        // Remove those additions before selecting another site's fallback order.
        $base = array_filter($paths, fn ($path) => !in_array(basename($path), $sites, true) || !in_array(dirname($path), $paths, true));
        $result = [];
        foreach ($base as $path) {
            if (is_dir($path.'/'.$site)) $result[] = $path.'/'.$site;
            $result[] = $path;
        }
        return array_values(array_unique($result));
    }

    public static function find($finder, mixed $name, string $directory): ?string
    {
        if (!is_string($name) || $name === '') return null;
        foreach ([$directory.'.'.$name, $name] as $candidate) {
            try { return $finder->find($candidate); } catch (\InvalidArgumentException) {}
        }
        return null;
    }
}
