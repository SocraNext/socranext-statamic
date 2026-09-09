{!! $assets !!}
<div id="socranext-artikel" class="socranext-root {{ $v2 ? 'sn-layout-v2' : '' }}">
    @if($v2 && ($settings['showProgress'] ?? false))<div class="sn-progress"><span class="sn-progress-bar"></span></div>@endif
    <main id="main" class="site-main {{ $v2 ? 'sn-main' : '' }}">
        <article class="sn-article {{ $v2 && ($settings['showToc'] ?? true) ? 'sn-has-rail' : '' }}">
            <header class="entry-header {{ $v2 ? 'sn-article-header' : '' }}">
                @if($v2 && ($settings['showBreadcrumbs'] ?? true))
                    <nav class="sn-breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="{{ \Statamic\Facades\Site::current()->absoluteUrl() }}">{{ ($settings['homeLabel'] ?? '') ?: 'Home' }}</a></li><li><a href="{{ $archiveUrl }}">{{ $archiveTitle }}</a></li><li><span aria-current="page">{{ $title }}</span></li></ol></nav>
                @endif
                @if($v2 && ($settings['showDate'] ?? true) && $entry?->date())<time class="sn-article-date" datetime="{{ $entry->date()->toIso8601String() }}">{{ $entry->date()->format('d-m-Y') }}</time>@endif
                <h1 class="entry-title {{ $v2 ? 'sn-article-title' : '' }}">{{ $title }}</h1>
                @if($hero !== '' && ($settings['showHero'] ?? true))<figure class="sn-article-hero"><img src="{{ $hero }}" alt="{{ $title }}"></figure>@endif
            </header>
            <div class="{{ $v2 ? 'sn-article-grid'.(($settings['showToc'] ?? true) ? '' : ' sn-article-grid--single') : '' }}">
                @if($v2 && ($settings['showToc'] ?? true) && count($toc))
                    <aside class="sn-article-rail"><nav class="sn-toc"><p class="sn-toc-title">{{ ($settings['tocTitle'] ?? '') ?: 'In dit artikel' }}</p><ol class="sn-toc-list">@foreach($toc as $heading)<li class="sn-toc-item sn-toc-level-{{ $heading['level'] }}"><a href="#{{ $heading['id'] }}">{{ $heading['text'] }}</a></li>@endforeach</ol></nav></aside>
                @endif
                <div class="{{ $v2 ? 'sn-article-body' : '' }}">
                    @if($v2 && ($settings['showTakeaways'] ?? true) && count($takeaways))<aside class="sn-takeaways"><p class="sn-takeaways-title">{{ ($settings['takeawaysTitle'] ?? '') ?: 'In het kort' }}</p><ul>@foreach($takeaways as $takeaway)<li>{{ is_scalar($takeaway) ? $takeaway : '' }}</li>@endforeach</ul></aside>@endif
                    @if($v2 && ($settings['ctaPosition'] ?? 'end') === 'both')@include('socranext::public.cta')@endif
                    <div class="entry-content {{ $v2 ? 'sn-article-content' : '' }}">{!! $body !!}</div>
                    @if($v2)@include('socranext::public.cta')@endif
                    @if($v2 && ($settings['showAuthor'] ?? false) && !empty($settings['authorName']))
                        <aside class="sn-author">
                            @if($safe->url((string) ($settings['authorImage'] ?? '')))<img class="sn-author-image" src="{{ $safe->url($settings['authorImage']) }}" alt="">@endif
                            <div class="sn-author-body"><p class="sn-author-name">{{ $settings['authorName'] }}</p><p class="sn-author-role">{{ $settings['authorRole'] ?? '' }}</p><p class="sn-author-bio">{{ $settings['authorBio'] ?? '' }}</p></div>
                        </aside>
                    @endif
                </div>
            </div>
            {!! $extra !!}
            @if($v2 && ($settings['showRelated'] ?? true) && count($related))<footer class="sn-article-footer"><h2 class="sn-related-title">{{ ($settings['relatedTitle'] ?? '') ?: 'Gerelateerde artikelen' }}</h2><ul class="sn-related-list">@foreach($related as $other)<li class="sn-related-item"><a href="{{ $safe->url((string) $other->absoluteUrl()) }}"><span class="sn-related-item-body"><span class="sn-related-item-title">{{ $other->get('title') }}</span><span class="sn-related-item-excerpt">{{ $other->get('socranext_meta_description') }}</span></span></a></li>@endforeach</ul></footer>@endif
        </article>
    </main>
</div>
{!! $faq !!}
@if($v2 && ($settings['showProgress'] ?? false))
<script>(function(){var bar=document.querySelector('#socranext-artikel .sn-progress-bar');function update(){var el=document.querySelector('#socranext-artikel'),height=el.offsetHeight-window.innerHeight,top=-el.getBoundingClientRect().top;bar.style.width=Math.max(0,Math.min(100,height>0?100*top/height:100))+'%';}window.addEventListener('scroll',update,{passive:true});update();})();</script>
@endif
