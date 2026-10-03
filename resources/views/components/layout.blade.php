<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · trend-analyzer</title>
    <link rel="stylesheet" href="{{ asset('css/clay.css') }}">
    <script type="module" src="{{ asset('js/series.js') }}"></script>
</head>
<body>
<main class="container">
    <nav class="nav" aria-label="Pages">
        <strong>trend-analyzer</strong>
        <a class="btn btn-secondary" href="{{ route('alarms') }}" @if (request()->routeIs('alarms')) aria-current="page" @endif>Alarms</a>
        <a class="btn btn-secondary" href="{{ route('sources') }}" @if (request()->routeIs('sources')) aria-current="page" @endif>Sources</a>
    </nav>
    @if (session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="notice" role="alert">{{ $errors->first() }}</p>
    @endif
    {{ $slot }}
</main>
</body>
</html>
