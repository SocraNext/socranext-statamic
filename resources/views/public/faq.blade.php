{!! $assets !!}
<div class="socranext-root socranext-frontend-block" id="socranext-frontend-block-{{ $id }}" data-socranext-render-mode="shortcode" data-socranext-rev="{{ $revision }}">
    <section class="socranext-qalist">
        @if($title !== '')<h2>{{ $title }}</h2>@endif
        <ul class="socranext-qa-list">
            @foreach($questions as $question)
                <li class="socranext-qa open">
                    <div class="socranext-q" tabindex="0" role="button" aria-expanded="true" aria-controls="sn-answer-{{ $id }}-{{ $loop->index }}">
                        <strong>{{ $question['question'] }}</strong>
                        <span class="socranext-toggle" aria-hidden="true"><span class="socranext-icon-collapsed">+</span><span class="socranext-icon-expanded">−</span></span>
                    </div>
                    <div class="socranext-a" style="max-height:none" id="sn-answer-{{ $id }}-{{ $loop->index }}">{!! $question['answer'] !!}</div>
                </li>
            @endforeach
        </ul>
        <div class="socranext-disclaimer">{!! $disclaimer !!}</div>
        {!! $extra !!}
    </section>
</div>
<script type="application/ld+json">{!! $schema !!}</script>
<script>
document.querySelectorAll('.socranext-qa .socranext-q:not([data-sn-bound])').forEach(function(head){
    head.dataset.snBound='1';
    head.parentElement.classList.remove('open');head.setAttribute('aria-expanded','false');head.parentElement.querySelector('.socranext-a').style.maxHeight='';
    function toggle(){var item=head.parentElement,answer=item.querySelector('.socranext-a'),open=item.classList.toggle('open');head.setAttribute('aria-expanded',String(open));answer.style.maxHeight=open?'none':'';}
    head.addEventListener('click',toggle);
    head.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){event.preventDefault();toggle();}});
});
</script>
