<?php

namespace SocraNext\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use SocraNext\Statamic\Content\{ArticlePublisher, ContentRepository, MutationLock};
use SocraNext\Statamic\Support\StateStore;
use Statamic\Facades\Entry;

class MetadataController
{
    public function __construct(private ContentRepository $content, private StateStore $store, private MutationLock $lock, private ArticlePublisher $publisher) {}

    public function update(Request $request, int|string $id)
    {
        $input = $request->validate([
            'type' => 'required|in:pages,blogs,posts,products,categories,custom', 'cpt' => 'nullable|string|max:128',
            'title' => 'sometimes|string|max:1024', 'slug' => 'sometimes|string|max:200|regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/',
            'metaDescription' => 'sometimes|string|max:10000', 'expected_revision' => 'sometimes|string|size:64',
        ]);
        $type = $input['type'] === 'blogs' ? 'posts' : $input['type'];
        $resource = $this->content->resolve($type, $id, $input['cpt'] ?? null);
        if (!$this->content->isTerm($resource) && $resource->collectionHandle() === $this->content->managedCollection()) {
            $payload = array_intersect_key($input, array_flip(['slug', 'metaDescription', 'expected_revision']));
            if (isset($input['title'])) $payload += ['titel' => $input['title'], 'titleTag' => $input['title']];
            return response()->json($this->publisher->update($id, $payload));
        }
        return $this->lock->run(function () use ($type, $id, $input) {
            $resource = $this->content->resolve($type, $id, $input['cpt'] ?? null);
            $term = $this->content->isTerm($resource);
            $revision = $this->content->fingerprint($resource);
            $expected = $input['expected_revision'] ?? ($this->store->get('metadata', [])[$id]['revision'] ?? null);
            abort_if($expected && !hash_equals($expected, $revision), 409, 'Content changed in Statamic. Refresh before editing metadata.');
            if (!$term) abort_if($resource->hasWorkingCopy(), 409, 'A Statamic editor has a pending working copy.');
            if ($term && isset($input['slug'])) abort_unless($input['slug'] === $resource->slug(), 422, 'Category slug changes require a native identity migration and are not supported by this connector version.');
            $candidate = clone $resource;
            $oldUrl = $resource->absoluteUrl();
            if (array_key_exists('title', $input)) $candidate->set('title', $input['title']);
            if (!$term && isset($input['slug'])) {
                $candidate->slug($input['slug']);
                $collision = Entry::findByUri($candidate->getQueryableValue('uri'), $candidate->locale());
                abort_if($collision && $collision->id() !== $candidate->id(), 409, 'The URL already belongs to another entry.');
            }
            if (!$term && $resource->revisionsEnabled()) $resource->makeRevision()->message('Before SocraNext metadata update')->save();
            abort_unless($candidate->save(), 422, 'Statamic rejected the metadata update.');
            $newUrl = $candidate->absoluteUrl();
            $this->store->transaction(function (array &$data) use ($candidate, $input, $id, $oldUrl, $newUrl) {
                if (array_key_exists('title', $input)) $data['metadata'][$id]['title_tag'] = $input['title'];
                if (array_key_exists('metaDescription', $input)) $data['metadata'][$id]['meta_description'] = $input['metaDescription'];
                $data['metadata'][$id]['revision'] = $this->content->fingerprint($candidate);
                if ($oldUrl && $newUrl && $oldUrl !== $newUrl) {
                    foreach ($data['redirects'] ?? [] as $old => $target) if ($target === $oldUrl) $data['redirects'][$old] = $newUrl;
                    $data['redirects'][$oldUrl] = $newUrl;
                    $data['redirects'] = array_filter($data['redirects'], fn ($target, $old) => $target !== $old, ARRAY_FILTER_USE_BOTH);
                }
            });
            if (config('statamic.static_caching.strategy')) \Statamic\Facades\StaticCache::flush();
            return response()->json([...$this->content->describe($candidate), 'success' => true]);
        });
    }
}
