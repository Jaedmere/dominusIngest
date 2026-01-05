@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Encabezado -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                Monitor del sistema
            </h1>
            <p class="text-sm text-slate-500">
                Estado de la base de datos y últimas líneas del registro.
            </p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('monitor') }}"
               class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white
                      hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-900/20">
                Actualizar
            </a>
        </div>
    </div>

    <!-- Tarjetas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Estado BD -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-slate-900">
                        Base de datos
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        Conectividad y respuesta
                    </div>
                </div>

                @if($dbOk)
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1
                                 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                        OK
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1
                                 text-xs font-semibold text-rose-700 ring-1 ring-rose-200">
                        FALLA
                    </span>
                @endif
            </div>

            @if(!$dbOk && $dbError)
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3">
                    <div class="text-xs font-semibold text-rose-800">
                        Detalle del error
                    </div>
                    <pre class="mt-2 text-[11px] leading-relaxed text-rose-900 whitespace-pre-wrap break-words">
{{ $dbError }}
                    </pre>
                </div>
            @else
                <div class="mt-4 text-sm text-slate-600">
                    Conexión establecida correctamente.
                </div>
            @endif
        </div>

        <!-- Información de logs -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-900">
                        Archivo de registro
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        Mostrando las últimas 200 líneas
                    </div>
                </div>

                <span class="mt-2 sm:mt-0 inline-flex max-w-full items-center rounded-xl bg-slate-50
                             px-3 py-1.5 text-[11px] font-mono text-slate-700 ring-1 ring-slate-200 overflow-hidden">
                    <span class="truncate">{{ $logPath }}</span>
                </span>
            </div>

            <!-- Visor de logs -->
            <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-950 text-slate-100 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
                    <div class="text-xs font-semibold tracking-wide text-white/80">
                        Eventos recientes
                    </div>
                    <div class="text-[11px] text-white/50">
                        Desplazamiento manual
                    </div>
                </div>

                <div class="max-h-[65vh] sm:max-h-[60vh] overflow-auto p-4">
                    <pre class="text-[11px] sm:text-xs leading-relaxed whitespace-pre-wrap break-words">
@foreach($lastLines as $line)
{{ $line }}
@endforeach
                    </pre>
                </div>
            </div>

            <!-- Nota -->
            <div class="mt-3 text-xs text-slate-500">
                Nota: si el archivo de registro crece demasiado, se puede optimizar la lectura
                cargando únicamente las últimas líneas sin consumir memoria innecesaria.
            </div>
        </div>
    </div>

</div>
@endsection
