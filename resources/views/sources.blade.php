<x-layout title="Sources">
    <header class="stack">
        <p class="eyebrow">Collection health</p>
        <h1>Sources</h1>
        <p class="lead">Every approved Source, what it is collected for, and whether it is working.</p>
    </header>

    <div class="lower" style="margin-block-start: var(--space-6)">
        @foreach ($sources as $source)
            <article class="tile">
                <span @class(['status', 'failing' => $source['last_error'], 'off' => ! $source['enabled']])>{{ $source['enabled'] ? ($source['last_error'] ? 'failing' : 'on') : 'off' }}</span>
                <h3>{{ $source['key'] }}</h3>
                <p>{{ implode(', ', $source['roles']) }}</p>
                <p>Last success: {{ $source['last_success_at'] ? \Illuminate\Support\Str::before($source['last_success_at'], 'T') : 'never' }}@if ($source['last_item_count'] !== null) · {{ $source['last_item_count'] }} Items @endif</p>
                <p>{{ $source['last_error'] ?? $source['note'] }}</p>
            </article>
        @endforeach
    </div>
</x-layout>
