@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Monitor</h1>
        <a href="{{ route('monitor') }}" class="underline">Refrescar</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 border rounded">
            <div class="font-semibold">Base de datos</div>
            @if($dbOk)
                <div class="text-green-700">OK</div>
            @else
                <div class="text-red-700">FALLA</div>
                <pre class="text-xs whitespace-pre-wrap mt-2">{{ $dbError }}</pre>
            @endif
        </div>

        <div class="p-4 border rounded">
            <div class="font-semibold">Log file</div>
            <div class="text-sm">{{ $logPath }}</div>
            <div class="text-xs text-slate-500 mt-1">Mostrando últimas 200 líneas</div>
        </div>
    </div>

    <div class="p-4 border rounded">
        <div class="font-semibold mb-2">Últimos logs</div>
        <pre class="text-xs whitespace-pre-wrap leading-relaxed">@foreach($lastLines as $line){{ $line . "\n" }}@endforeach</pre>
    </div>
</div>
@endsection
