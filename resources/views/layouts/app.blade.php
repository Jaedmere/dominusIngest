<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Dominus Ingest') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-slate-50 text-slate-900">
    <div class="min-h-screen flex flex-col">
        @include('layouts.navigation')

        @hasSection('header')
            <header class="bg-white border-b border-slate-200">
                <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-4">
                    @yield('header')
                </div>
            </header>
        @endif

        <main class="flex-1">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                @yield('content')
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
                    <div class="text-xs text-slate-500">
                        © {{ date('Y') }} {{ config('app.name', 'Dominus Ingest') }}
                    </div>
                    <div class="text-xs text-slate-500">
                        Entorno: {{ app()->environment() }}
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
