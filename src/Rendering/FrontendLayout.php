<?php

namespace SocraNext\Statamic\Rendering;

use SocraNext\Statamic\Content\ContentRepository;

class FrontendLayout
{
    public static function exists(string $name): bool
    {
        return $name !== '' && (view()->exists('layouts.'.$name) || view()->exists($name));
    }

    public static function resolve(): string
    {
        $layout = config('socranext.content.article_layout', 'layout');
        abort_unless(is_string($layout) && $layout !== '', 422, 'Choose an article layout.');
        if ($layout === 'layout') {
            $native = config('statamic.system.layout', 'layout');
            if (is_string($native) && self::existsForConnectedSites($native, true)) return $native;
            // Native Antlers layouts receive template_content, including through partials.
            // A Blade @yield shell cannot consume our Antlers template automatically.
            return 'socranext::public.layout';
        }
        if (self::existsForConnectedSites($layout)) return $layout;
        abort(422, 'The configured article layout does not exist.');
    }

    private static function existsForConnectedSites(string $name, bool $antlersOnly = false): bool
    {
        foreach (app(ContentRepository::class)->sites() as $site) {
            $path = SiteViewFinder::find(SiteViewFinder::forSite($site), $name, 'layouts');
            if (!$path || ($antlersOnly && !str_ends_with($path, '.antlers.html') && !str_ends_with($path, '.antlers.php'))) return false;
        }
        return true;
    }
}
