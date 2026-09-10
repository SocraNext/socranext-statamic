<?php

namespace SocraNext\Statamic\Rendering;

use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Support\{Readiness, StateStore};
use Statamic\Contracts\Entries\Entry;
use Statamic\Events\ResponseCreated;
use Statamic\Taxonomies\LocalizedTerm;

/** Automatic output for public native Statamic HTML responses only. */
class AutomaticFrontend
{
    public function __construct(private Renderer $renderer, private AutomaticHtml $html, private ContentRepository $content, private Readiness $readiness, private StateStore $store) {}

    public function handle(ResponseCreated $event): void
    {
        try {
            $this->render($event);
        } catch (\Throwable $exception) {
            // An optional integration must never take the customer's native website down.
            try {
                \Illuminate\Support\Facades\Log::warning('SocraNext automatic frontend was skipped.', ['exception_class' => get_class($exception)]);
            } catch (\Throwable) {
                // A broken host logger must not turn this fallback into a website failure.
            }
        }
    }

    private function render(ResponseCreated $event): void
    {
        if (config('socranext.frontend.mode', 'automatic') !== 'automatic') return;
        $request = request();
        $response = $event->response;
        $resource = $event->data;
        if (!$request->isMethod('GET') || !$response->isSuccessful()
            // DataResponse dispatches before Laravel fills its default HTML content type.
            || strtolower(trim(explode(';', (string) $response->headers->get('Content-Type', 'text/html'))[0])) !== 'text/html'
            || (!$resource instanceof Entry && !$resource instanceof LocalizedTerm)) return;
        foreach (['api', 'socranext', trim((string) config('statamic.cp.route', 'cp'), '/'), trim((string) config('statamic.routes.action', '!'), '/')] as $prefix) {
            if ($prefix !== '' && $request->is($prefix, $prefix.'/*')) return;
        }
        if ($request->isLivePreview() || $request->has('token') || $request->has('socranext_token') || $request->headers->has('X-Statamic-Token')) return;
        foreach (['X-Statamic-Protected', 'X-Statamic-Draft', 'X-Statamic-Private'] as $header) {
            if ($response->headers->has($header)) return;
        }
        if (!$this->content->publiclyDiscoverable($resource)) return;
        $managed = $resource instanceof Entry && $resource->collectionHandle() === $this->content->managedCollection()
            && $resource->get('socranext_owned') === true;
        $id = $this->existingPresentationId($resource);
        if ($id === null && !$managed) return;
        if (!$this->readiness->ready()) return;
        $id ??= $this->content->identity($resource);
        $fields = ['title', 'description', 'canonical', 'schema'];
        if (!$managed) {
            $override = $this->store->get('metadata', [])[$id] ?? null;
            $fields = is_array($override) ? ['canonical'] : [];
            foreach (['title_tag' => 'title', 'meta_description' => 'description', 'json_ld' => 'schema'] as $key => $field) {
                if (is_array($override) && array_key_exists($key, $override)) $fields[] = $field;
            }
        }
        // Numeric identities include the native site, including localized taxonomy terms.
        $output = $this->html->render($response->getContent(), $this->renderer->faq($id), 'socranext-frontend-block-'.$id, $this->renderer->metadata($resource), $fields);
        if ($output !== $response->getContent()) {
            $response->setContent($output);
            // Native content may supply these headers; the rendered representation changed.
            $response->headers->remove('Content-Length');
            $response->headers->remove('ETag');
            $response->headers->remove('Last-Modified');
        }
    }

    /** Public visits never allocate registry records for unrelated customer pages. */
    private function existingPresentationId($resource): ?int
    {
        $kind = $resource instanceof LocalizedTerm ? 'term' : 'entry';
        return $this->store->transaction(function (array &$state) use ($resource, $kind) {
            if (empty($state['faqs']) && empty($state['metadata'])) return null;
            // Read IdentityMap's existing index; never allocate an ID while serving a page.
            $identity = [$kind, (string) $resource->id(), $resource->locale()];
            $key = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
            $id = $state['identities']['keys'][$key] ?? null;
            if (!is_int($id) || $id < 1) return null;
            $record = $state['identities']['records'][$id] ?? null;
            return is_array($record) && !($record['deleted'] ?? false)
                && [$record['kind'] ?? null, $record['native_id'] ?? null, $record['site'] ?? null] === $identity
                && (isset($state['faqs'][$id]) || isset($state['metadata'][$id])) ? $id : null;
        });
    }
}
