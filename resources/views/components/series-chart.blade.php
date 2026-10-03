@props(['source', 'weeks'])
@php($chart = new \App\Http\SeriesChart($weeks))
<figure class="tile chart" data-points='@json($chart->points)'>
    <figcaption><h3>{{ $source }}</h3><p>Weekly Volume, {{ count($weeks) }} weeks</p></figcaption>
    <svg viewBox="0 0 {{ \App\Http\SeriesChart::WIDTH }} {{ \App\Http\SeriesChart::HEIGHT }}" role="img" aria-label="{{ $source }} weekly Volume">
        <line class="grid" x1="0" x2="{{ \App\Http\SeriesChart::WIDTH }}" y1="{{ $chart->top() }}" y2="{{ $chart->top() }}" />
        <line class="grid" x1="0" x2="{{ \App\Http\SeriesChart::WIDTH }}" y1="{{ $chart->baseline() }}" y2="{{ $chart->baseline() }}" />
        <text class="axis" x="0" y="{{ $chart->top() - 3 }}">{{ number_format($chart->max) }}</text>
        <path class="wash" d="{{ $chart->area() }}" />
        <path class="line" d="{{ $chart->path() }}" />
        @if ($last = $chart->last())
            <circle class="dot" cx="{{ $last['x'] }}" cy="{{ $last['y'] }}" r="4" />
            <text class="axis" x="{{ $last['x'] + 8 }}" y="{{ $last['y'] + 4 }}">{{ number_format($last['volume']) }}</text>
        @endif
        @if ($chart->points !== [])
            <text class="axis" x="0" y="{{ \App\Http\SeriesChart::HEIGHT - 4 }}">{{ $chart->points[0]['week'] }}</text>
            <text class="axis" x="{{ \App\Http\SeriesChart::WIDTH - 44 }}" y="{{ \App\Http\SeriesChart::HEIGHT - 4 }}" text-anchor="end">{{ \Illuminate\Support\Arr::last($chart->points)['week'] }}</text>
        @endif
        <line class="crosshair" x1="0" x2="0" y1="{{ $chart->top() }}" y2="{{ $chart->baseline() }}" visibility="hidden" />
    </svg>
</figure>
