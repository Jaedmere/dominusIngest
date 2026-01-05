<x-guest-layout>
    <div class="min-h-screen flex items-stretch bg-slate-950">
        <!-- Panel izquierdo (solo escritorio) -->
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-fuchsia-600/20 via-indigo-500/10 to-cyan-400/10"></div>
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-fuchsia-500/20 blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-cyan-400/20 blur-3xl"></div>

            <div class="relative z-10 p-10 flex flex-col justify-between w-full">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/10 border border-white/10 grid place-items-center">
                        <span class="text-white font-black">DI</span>
                    </div>
                    <div>
                        <div class="text-white font-semibold leading-tight">Dominus Ingest</div>
                        <div class="text-white/70 text-sm">ETL seguro para Power BI</div>
                    </div>
                </div>

                <div class="max-w-md">
                    <h1 class="text-4xl font-bold text-white tracking-tight">
                        Monitoreo y carga de ventas, sin drama.
                    </h1>
                    <p class="mt-4 text-white/70 leading-relaxed">
                        Ingesta por fecha, deduplicación, logs y control de ejecuciones.
                        Hecho para correr en VPS sin colgar conexiones.
                    </p>

                    <div class="mt-8 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="text-white font-semibold">Registros</div>
                            <div class="text-white/70 mt-1">Últimas 200 líneas</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="text-white font-semibold">Ejecuciones</div>
                            <div class="text-white/70 mt-1">OK / Fallidas / Totales</div>
                        </div>
                    </div>
                </div>

                <div class="text-white/50 text-xs">© {{ date('Y') }} Dominus Ingest</div>
            </div>
        </div>

        <!-- Panel derecho (inicio de sesión) -->
        <div class="w-full lg:w-1/2 flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md">
                <div class="rounded-3xl border border-white/10 bg-white/5 backdrop-blur-xl shadow-2xl shadow-black/30 p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-11 h-11 rounded-2xl bg-white/10 border border-white/10 grid place-items-center">
                            <svg viewBox="0 0 24 24" class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 3v18" />
                                <path d="M6 9h12" />
                                <path d="M6 15h12" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-white font-semibold text-lg leading-tight">Iniciar sesión</div>
                            <div class="text-white/60 text-sm">Acceso al monitor de ingesta</div>
                        </div>
                    </div>

                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    <form method="POST" action="{{ route('login') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="text-sm font-medium text-white/80">Correo</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="tu@correo.com"
                                class="mt-1 block w-full rounded-2xl bg-white/10 border border-white/10 text-white placeholder-white/40
                                       focus:outline-none focus:ring-4 focus:ring-cyan-400/20 focus:border-cyan-300 px-4 py-3"
                            />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <label for="password" class="text-sm font-medium text-white/80">Contraseña</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="mt-1 block w-full rounded-2xl bg-white/10 border border-white/10 text-white placeholder-white/40
                                       focus:outline-none focus:ring-4 focus:ring-cyan-400/20 focus:border-cyan-300 px-4 py-3"
                            />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <label for="remember_me" class="inline-flex items-center gap-2 select-none">
                                <input
                                    id="remember_me"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-400/30"
                                    name="remember"
                                >
                                <span class="text-sm text-white/70">Recordarme</span>
                            </label>

                            @if (Route::has('password.request'))
                                <a class="text-sm text-white/70 hover:text-white underline decoration-white/30 hover:decoration-white"
                                   href="{{ route('password.request') }}">
                                    ¿Olvidaste tu contraseña?
                                </a>
                            @endif
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-2xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-semibold py-3
                                   focus:outline-none focus:ring-4 focus:ring-cyan-400/30 transition"
                        >
                            Entrar
                        </button>

                        <p class="text-xs text-white/50 leading-relaxed pt-2">
                            Si algo falla, entra y revisa <span class="text-white/70">/monitor</span> para ver estado de base de datos y registros.
                        </p>
                    </form>
                </div>

                <div class="lg:hidden text-center text-white/40 text-xs mt-6">
                    Dominus Ingest · {{ date('Y') }}
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
