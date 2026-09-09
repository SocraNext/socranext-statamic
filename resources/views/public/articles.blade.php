{!! $assets !!}
<main id="socranext-archive" class="socranext-root"><h1 class="socranext-archive-title">{{ $title }}</h1><div class="socranext-articles">
    @foreach($cards as $card)<article>
        @if($safe->url((string) $card['image']))<a href="{{ $safe->url((string) $card['url']) }}"><img src="{{ $safe->url((string) $card['image']) }}" alt="" loading="lazy"></a>@endif
        <h2><a href="{{ $safe->url((string) $card['url']) }}">{{ $card['title'] }}</a></h2><div class="excerpt">{{ $card['excerpt'] }}</div><a class="socranext-read-more" href="{{ $safe->url((string) $card['url']) }}">{{ $button }}</a>
    </article>@endforeach
</div>{!! $extra !!}</main>
