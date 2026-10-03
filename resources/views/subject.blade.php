<x-layout :title="$subject['name']">
    <header class="stack">
        <p class="eyebrow">{{ $subject['state'] }} · first seen {{ $subject['first_seen_on'] }}</p>
        <h1>{{ $subject['name'] }}</h1>
        <p class="lead">Measured as “{{ $subject['query'] }}”.@if ($subject['lead_time_days'] !== null) Reached the mainstream {{ $subject['lead_time_days'] }} days after its first Alarm.@endif</p>
        <div class="chips">@foreach ($subject['labels'] as $label)<span class="chip">{{ $label }}</span>@endforeach</div>
    </header>

    @if ($subject['score'])
        <section class="panel">
            <div class="panel-head"><h2>Week of {{ $subject['scored_week'] }}</h2></div>
            <div class="metric-grid">
                <div class="metric"><strong>{{ $subject['score']['trend_score'] }}</strong><span>Trend Score</span></div>
                <div class="metric"><strong>{{ $subject['score']['corroboration'] }}</strong><span>Sources rising</span></div>
                @foreach ($subject['score']['velocities'] as $source => $velocity)
                    <div class="metric"><strong>{{ $velocity }}</strong><span>{{ $source }} Velocity</span></div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="panel-head"><h2>Weekly series</h2></div>
        <div class="panel-body lower">
            @forelse ($subject['series'] as $source => $weeks)
                <x-series-chart :source="$source" :weeks="$weeks" />
            @empty
                <p class="muted">Not measured yet: the next weekly run backfills it.</p>
            @endforelse
        </div>
        @if ($subject['series'] !== [])
            <details class="panel-body">
                <summary>Series as a table</summary>
                <div class="scroll">
                    <table>
                        <thead><tr><th scope="col">Week</th>@foreach (array_keys($subject['series']) as $source)<th scope="col">{{ $source }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach (collect($subject['series'])->flatMap(fn ($weeks) => array_keys($weeks))->unique()->sort() as $week)
                                <tr><td>{{ $week }}</td>@foreach ($subject['series'] as $weeks)<td>{{ isset($weeks[$week]) ? number_format($weeks[$week]) : '—' }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><h2>What happened</h2></div>
        <ul class="list">
            @foreach (array_reverse($subject['events']) as $event)
                <li><strong>{{ $event['to'] ?? $event['type'] }}</strong> — {{ $event['reason'] ?? $event['type'] }} <span class="muted">{{ \Illuminate\Support\Str::before($event['at'], 'T') }}</span></li>
            @endforeach
        </ul>
    </section>

    @if ($subject['items'] !== [])
        <section class="panel">
            <div class="panel-head"><h2>Recent mentions</h2></div>
            <ul class="list">
                @foreach ($subject['items'] as $item)
                    <li><a href="{{ $item['url'] }}" rel="noreferrer">{{ $item['title'] }}</a> <span class="muted">{{ $item['source'] }} · {{ $item['observed_on'] }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layout>
