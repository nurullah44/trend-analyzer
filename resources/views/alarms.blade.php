<x-layout title="Alarms">
    <header class="stack">
        <p class="eyebrow">Week of {{ $report['week'] }}</p>
        <h1>This week's Alarms</h1>
        <p class="lead">Subjects whose rise is corroborated by more than one Source. Evidence only — the ruling is yours.</p>
    </header>

    @forelse ($report['alarms'] as $alarm)
        <section class="panel" aria-labelledby="alarm-{{ $alarm['id'] }}">
            <div class="panel-head">
                <div>
                    <h2 id="alarm-{{ $alarm['id'] }}"><a href="{{ route('subject', $alarm['slug']) }}">{{ $alarm['subject'] }}</a></h2>
                    <div class="chips">@foreach ($alarm['labels'] as $label)<span class="chip">{{ $label }}</span>@endforeach</div>
                </div>
                <span class="status">{{ $alarm['verdict'] ? str_replace('_', ' ', $alarm['verdict']) : $alarm['state'] }}</span>
            </div>
            <div class="metric-grid">
                <div class="metric"><strong>{{ $alarm['trend_score'] }}</strong><span>Trend Score</span></div>
                <div class="metric"><strong>{{ $alarm['corroboration'] }}</strong><span>Sources rising</span></div>
                @foreach ($alarm['evidence']['volumes'] ?? [] as $source => $volume)
                    <div class="metric"><strong>{{ number_format($volume) }}</strong><span>{{ $source }} this week</span></div>
                @endforeach
            </div>
            <div class="panel-body stack">
                @if (! empty($alarm['evidence']['items']))
                    <ul class="list">
                        @foreach ($alarm['evidence']['items'] as $item)
                            <li><a href="{{ $item['url'] }}" rel="noreferrer">{{ $item['title'] }}</a> <span class="muted">{{ $item['source'] }} · {{ $item['measured_quantity'] }}</span></li>
                        @endforeach
                    </ul>
                @endif
                <form class="verdict" method="post" action="{{ route('verdict', $alarm['id']) }}">
                    @csrf
                    <div class="field">
                        <label for="verdict-{{ $alarm['id'] }}">Verdict</label>
                        <select id="verdict-{{ $alarm['id'] }}" name="verdict">
                            @foreach (\App\Enums\Verdict::cases() as $verdict)
                                <option value="{{ $verdict->value }}" @selected($alarm['verdict'] === $verdict->value)>{{ str_replace('_', ' ', $verdict->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="magnitude-{{ $alarm['id'] }}">Magnitude</label>
                        <select id="magnitude-{{ $alarm['id'] }}" name="magnitude">
                            <option value="">—</option>
                            @foreach (\App\Enums\Magnitude::cases() as $magnitude)
                                <option value="{{ $magnitude->value }}">{{ $magnitude->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="note-{{ $alarm['id'] }}">One-line reason</label>
                        <input id="note-{{ $alarm['id'] }}" name="note" value="{{ $alarm['verdict_note'] }}" maxlength="500">
                    </div>
                    <button class="btn btn-primary" type="submit">Record</button>
                </form>
            </div>
        </section>
    @empty
        <p class="notice">No Alarms this week.</p>
    @endforelse

    @if ($report['gaps'] !== [])
        <section class="panel">
            <div class="panel-head"><h2>Gaps</h2></div>
            <ul class="list">@foreach ($report['gaps'] as $gap)<li>{{ $gap }}</li>@endforeach</ul>
        </section>
    @endif
</x-layout>
