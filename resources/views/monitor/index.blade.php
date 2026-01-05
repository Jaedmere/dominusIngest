@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Encabezado -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">Monitor del sistema</h1>
            <p class="text-sm text-slate-500">Estado de base de datos, ejecuciones y registro del cron.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('monitor') }}"
               class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white
                      hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-900/20">
                Actualizar
            </a>
        </div>
    </div>

    <!-- KPIs + Estado BD -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Estado BD -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Base de datos</div>
                    <div class="text-xs text-slate-500 mt-1">Conectividad y respuesta</div>
                </div>

                @if($dbOk)
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                        OK
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-200">
                        FALLA
                    </span>
                @endif
            </div>

            @if(!$dbOk && $dbError)
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3">
                    <div class="text-xs font-semibold text-rose-800">Detalle del error</div>
                    <pre class="mt-2 text-[11px] leading-relaxed text-rose-900 whitespace-pre-wrap break-words">{{ $dbError }}</pre>
                </div>
            @else
                <div class="mt-4 text-sm text-slate-600">Conexión establecida correctamente.</div>
            @endif
        </div>

        <!-- KPIs runs -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Ejecuciones recientes</div>
                    <div class="text-xs text-slate-500 mt-1">Resumen sobre las últimas 15 ejecuciones</div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">OK</div>
                    <div class="text-lg font-bold text-slate-900">{{ $totals['ok'] }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Fallidas</div>
                    <div class="text-lg font-bold text-slate-900">{{ $totals['failed'] }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">En ejecución</div>
                    <div class="text-lg font-bold text-slate-900">{{ $totals['running'] }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Insertadas</div>
                    <div class="text-lg font-bold text-slate-900">{{ $totals['inserted'] }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Actualizadas</div>
                    <div class="text-lg font-bold text-slate-900">{{ $totals['updated'] }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs text-slate-500">Omitidas</div>
                    <div class="text-lg font-bold text-slate-900">{{ $totals['skipped'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- RUNS: móvil (cards) + desktop (tabla) -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">Detalle de ejecuciones</div>
                <div class="text-xs text-slate-500 mt-1">Últimas 15 filas de dominus_runs</div>
            </div>
        </div>

        <!-- MÓVIL: TARJETAS -->
        <div class="mt-4 space-y-3 lg:hidden">
            @forelse($runs as $r)
                @php
                    $badge = match($r->status) {
                        'ok' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                        'failed' => 'bg-rose-50 text-rose-700 ring-rose-200',
                        default => 'bg-amber-50 text-amber-800 ring-amber-200',
                    };
                @endphp

                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">
                                {{ \Illuminate\Support\Carbon::parse($r->date)->format('Y-m-d') }}
                            </div>
                            <div class="text-xs text-slate-500 mt-1">
                                Inicio: {{ $r->started_at ? \Illuminate\Support\Carbon::parse($r->started_at)->format('H:i:s') : '—' }}
                                · Fin: {{ $r->finished_at ? \Illuminate\Support\Carbon::parse($r->finished_at)->format('H:i:s') : '—' }}
                            </div>
                        </div>

                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $badge }}">
                            {{ strtoupper($r->status) }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-2">
                            <div class="text-slate-500">Insert</div>
                            <div class="font-bold text-slate-900">{{ (int)$r->inserted }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-2">
                            <div class="text-slate-500">Update</div>
                            <div class="font-bold text-slate-900">{{ (int)$r->updated }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-2">
                            <div class="text-slate-500">Omit</div>
                            <div class="font-bold text-slate-900">{{ (int)$r->skipped }}</div>
                        </div>
                    </div>

                    @if(!empty($r->error))
                        <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3">
                            <div class="text-xs font-semibold text-rose-800">Error</div>
                            <div class="mt-1 text-[11px] text-rose-900 break-words whitespace-pre-wrap">{{ $r->error }}</div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-sm text-slate-500">No hay ejecuciones registradas.</div>
            @endforelse
        </div>

        <!-- DESKTOP: TABLA -->
        <div class="mt-4 hidden lg:block">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-slate-500 border-b">
                        <tr>
                            <th class="py-3 pr-4 text-left">Fecha</th>
                            <th class="py-3 pr-4 text-left">Estado</th>
                            <th class="py-3 pr-4 text-left">Inicio</th>
                            <th class="py-3 pr-4 text-left">Fin</th>
                            <th class="py-3 pr-4 text-right">Insertadas</th>
                            <th class="py-3 pr-4 text-right">Actualizadas</th>
                            <th class="py-3 pr-4 text-right">Omitidas</th>
                            <th class="py-3 pr-0 text-left">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($runs as $r)
                            @php
                                $badge = match($r->status) {
                                    'ok' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'failed' => 'bg-rose-50 text-rose-700 ring-rose-200',
                                    default => 'bg-amber-50 text-amber-800 ring-amber-200',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="py-3 pr-4 font-medium text-slate-900">{{ $r->date }}</td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $badge }}">
                                        {{ strtoupper($r->status) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-slate-700">{{ $r->started_at }}</td>
                                <td class="py-3 pr-4 text-slate-700">{{ $r->finished_at }}</td>
                                <td class="py-3 pr-4 text-right font-semibold text-slate-900">{{ (int)$r->inserted }}</td>
                                <td class="py-3 pr-4 text-right font-semibold text-slate-900">{{ (int)$r->updated }}</td>
                                <td class="py-3 pr-4 text-right font-semibold text-slate-900">{{ (int)$r->skipped }}</td>
                                <td class="py-3 pr-0 text-[12px] text-slate-600 whitespace-pre-wrap break-words max-w-xl">
                                    {{ $r->error }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-center text-slate-500">No hay ejecuciones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- LOG DEL CRON -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">Registro del cron</div>
                <div class="text-xs text-slate-500 mt-1">Mostrando las últimas líneas (lo último arriba)</div>
            </div>

            <span class="mt-2 sm:mt-0 inline-flex max-w-full items-center rounded-xl bg-slate-50 px-3 py-1.5 text-[11px] font-mono text-slate-700 ring-1 ring-slate-200 overflow-hidden">
                <span class="truncate">{{ $logPath }}</span>
            </span>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-950 text-slate-100 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
                <div class="text-xs font-semibold tracking-wide text-white/80">Eventos recientes</div>
                <div class="text-[11px] text-white/50">Desplazamiento manual</div>
            </div>

            <div class="max-h-[70vh] sm:max-h-[60vh] overflow-auto p-4">
                <pre class="text-[11px] sm:text-xs leading-relaxed whitespace-pre-wrap break-words">
@foreach($lastLines as $line)
{{ $line }}
@endforeach
                </pre>
            </div>
        </div>
    </div>

</div>
@endsection
