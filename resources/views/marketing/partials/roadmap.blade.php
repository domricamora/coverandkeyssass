<ol class="roadmap">
    @foreach ($roadmap as $item)
        <li class="roadmap__item roadmap__item--{{ $item['status'] }}">
            <span class="roadmap__phases">Phase {{ $item['phases'] }}</span>
            <div>
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['summary'] }}</p>
            </div>
            <span class="roadmap__status">
                @switch($item['status'])
                    @case('complete') Shipped @break
                    @case('current') In progress @break
                    @case('next') Next @break
                    @default Planned
                @endswitch
            </span>
        </li>
    @endforeach
</ol>
