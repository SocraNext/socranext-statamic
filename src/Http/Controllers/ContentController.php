<?php

namespace SocraNext\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use SocraNext\Statamic\Content\{ArticlePublisher, ContentRepository};
use Statamic\Facades\{Collection, Site};

class ContentController
{
    public function __construct(private ContentRepository $content, private ArticlePublisher $publisher) {}

    public function index(Request $request, string $type)
    {
        return $this->listing($this->content->listing($type, $this->listParameters($request)));
    }

    public function show(Request $request, string $type, int|string $id)
    {
        return response()->json($this->content->describe($this->content->resolve($type, $id)));
    }

    public function cptIndex(Request $request, string $cpt)
    {
        abort_unless($this->content->collections('custom', $cpt), 404, 'Collection is not exposed.');
        return $this->listing($this->content->listing('custom', $this->listParameters($request), $cpt));
    }

    public function cptShow(Request $request, string $cpt, int|string $id)
    {
        return response()->json($this->content->describe($this->content->resolve('custom', $id, $cpt)));
    }

    public function languages(Request $request)
    {
        return response()->json(array_map(function ($handle) {
            $site = Site::get($handle);
            return ['code' => $this->content->languageCode($handle), 'locale' => $site->locale(), 'site' => $handle,
                'name' => $site->name(), 'default' => $handle === Site::default()->handle(), 'flag' => '', 'home' => $site->absoluteUrl()];
        }, $this->content->sites()));
    }

    public function translations(Request $request)
    {
        $params = $request->validate(['id' => 'required|integer|min:1', 'kind' => 'nullable|in:post,term', 'type' => 'nullable|string|max:128']);
        $type = $params['type'] ?? 'page';
        $kind = $params['kind'] ?? 'post';
        $resourceType = $kind === 'term' ? 'categories' : (['page' => 'pages', 'post' => 'posts', 'product' => 'products'][$type] ?? 'custom');
        $resource = $this->content->resolve($resourceType, $params['id'], $resourceType === 'custom' ? $type : null);
        $root = $kind === 'term' ? $resource : $resource->root();
        $group = $this->content->identity($root);
        $items = [];
        foreach ($this->content->sites() as $site) {
            $item = $resource->in($site);
            if (!$item || ($kind === 'term' && $item->data()->isEmpty())) continue;
            if (!$this->content->publiclyDiscoverable($item)) continue;
            $items[] = [...$this->content->describe($item), 'trid' => $group];
        }
        return response()->json($items);
    }

    public function postTypes(Request $request)
    {
        $params = $this->listParameters($request);
        $custom = [];
        foreach ([...$this->content->customCollections(), $this->content->managedCollection()] as $handle) {
            $collection = Collection::find($handle);
            if (!$collection) continue;
            if ($handle !== $this->content->managedCollection() && !$this->content->listing('custom', [...$params, 'per_page' => 1], $handle)['total']) continue;
            $custom[] = ['name' => $handle === $this->content->managedCollection() ? 'socranext_post' : $handle,
                'label' => $collection->title(), 'singular_name' => $collection->title()];
        }
        $availability = [];
        foreach (['pages', 'posts', 'products', 'categories'] as $type) $availability[$type === 'posts' ? 'blogs' : $type] = $this->content->listing($type, $params)['total'] > 0;
        return response()->json(['postTypes' => $custom, 'availability' => $availability]);
    }

    public function searchUrl(Request $request)
    {
        $params = $this->listParameters($request);
        $items = [];
        $types = [['pages', null], ['posts', null], ['products', null], ['categories', null], ['custom', 'socranext_post']];
        foreach ($this->content->customCollections() as $handle) $types[] = ['custom', $handle];
        foreach ($types as [$type, $cpt]) {
            $page = 1;
            do {
                $part = $this->content->listing($type, [...$params, 'page' => $page, 'per_page' => 100], $cpt);
                foreach ($part['items'] as $item) $items[$item['id']] = $item;
            } while ($page++ < $part['totalPages']);
        }
        $items = array_values($items);
        usort($items, fn ($a, $b) => [$a['title'], $a['id']] <=> [$b['title'], $b['id']]);
        $perPage = (int) ($params['per_page'] ?? 100);
        return $this->listing(['items' => array_slice($items, ((int) ($params['page'] ?? 1) - 1) * $perPage, $perPage),
            'total' => count($items), 'totalPages' => (int) ceil(count($items) / $perPage)]);
    }

    public function publish(Request $request)
    {
        return response()->json($this->publisher->publish($this->articlePayload($request, true)));
    }

    public function update(Request $request, int|string $id)
    {
        return response()->json($this->publisher->update($id, $this->articlePayload($request, false)));
    }

    public function delete(Request $request, int|string $id)
    {
        return response()->json($this->publisher->delete($id));
    }

    public function createCategory(Request $request)
    {
        return response()->json($this->publisher->createCategory($request->validate([
            'name' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'slug' => 'sometimes|string|max:200',
            'site' => 'nullable|string|max:128', 'language' => 'nullable|string|max:128', 'sourceTermId' => 'nullable|integer|min:1',
        ])));
    }

    public function deleteCategory(Request $request, int|string $id)
    {
        return response()->json($this->publisher->deleteCategory($id));
    }

    public function purge(Request $request)
    {
        return response()->json($this->publisher->purge());
    }

    public function collectionSlugs(Request $request)
    {
        return response()->json($this->publisher->collectionSlugs($request->validate([
            'articles_slug' => 'required|string|max:200', 'old_articles_slug' => 'nullable|string|max:200',
            'site' => 'nullable|string|max:128', 'language' => 'nullable|string|max:128',
        ])));
    }

    private function listParameters(Request $request): array
    {
        return $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'nullable|string|max:2048', 'lang' => 'nullable|string|max:128', 'site' => 'nullable|string|max:128']);
    }

    private function articlePayload(Request $request, bool $create): array
    {
        return $request->validate([
            'blogId' => [$create ? 'required' : 'sometimes', function ($attribute, $value, $fail) { if ((!is_string($value) && !is_int($value)) || strlen((string) $value) > 256) $fail('blogId must be a string or integer of at most 256 characters.'); }],
            'titel' => ($create ? 'required' : 'sometimes').'|string|max:1024', 'tekst' => ($create ? 'required' : 'sometimes').'|string|max:5000000',
            'slug' => 'sometimes|string|max:200', 'parentSlug' => 'nullable|string|max:200',
            'categorie' => ['nullable', function ($attribute, $value, $fail) { if ((!is_string($value) && !is_int($value)) || strlen((string) $value) > 255) $fail('categorie must be a numeric ID or bounded category name.'); }],
            'categoryName' => 'nullable|string|max:255', 'titleTag' => 'nullable|string|max:1024', 'metaDescription' => 'nullable|string|max:10000',
            'jsonLd' => ['nullable', function ($attribute, $value, $fail) {
                if (is_string($value)) { try { $value = json_decode($value, true, 32, JSON_THROW_ON_ERROR); } catch (\JsonException) { $fail('jsonLd must be valid JSON.'); return; } }
                if (!is_array($value) || strlen(json_encode($value)) > 200000) $fail('jsonLd must be a bounded JSON object or array.');
            }], 'keyTakeaways' => 'nullable|array|max:50', 'keyTakeaways.*' => 'string|max:5000', 'author' => 'nullable|string|max:1024',
            'featuredImageUrl' => 'nullable|string|max:4096', 'featuredImageTitle' => 'nullable|string|max:1024',
            'featuredImageAlt' => 'nullable|string|max:1024', 'featuredImageFilename' => 'nullable|string|max:255',
            'site' => 'nullable|string|max:128', 'language' => 'nullable|string|max:128', 'sourcePostId' => 'nullable|integer|min:1',
            'sourceTermId' => 'nullable|integer|min:1',
            'published' => 'sometimes|boolean', 'status' => 'sometimes|in:draft,publish,published', 'expected_revision' => 'sometimes|string|size:64',
            'restore' => 'sometimes|boolean',
        ]);
    }

    private function listing(array $result)
    {
        return response()->json($result['items'])->header('X-WP-Total', (string) $result['total'])
            ->header('X-WP-TotalPages', (string) $result['totalPages']);
    }
}
