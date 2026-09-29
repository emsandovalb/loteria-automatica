<x-guest-layout>
    <main class="login-stage">
        <div class="login-bg-photo" aria-hidden="true"></div>
        <div class="login-wash" aria-hidden="true"></div>
        <div class="login-lines" aria-hidden="true"></div>
        <div class="login-ball login-ball-15" aria-hidden="true">15</div>
        <div class="login-ball login-ball-68" aria-hidden="true">68</div>
        <div class="login-ball login-ball-89" aria-hidden="true">89</div>
        <div class="login-ball login-ball-28" aria-hidden="true">28</div>
        <div class="login-dots login-dots-top" aria-hidden="true"></div>
        <div class="login-dots login-dots-left" aria-hidden="true"></div>
        <div class="login-dots login-dots-right" aria-hidden="true"></div>

        <section class="relative z-10 mx-auto flex min-h-screen w-full max-w-[1440px] flex-col px-5 py-8 text-brand-navy sm:px-8 lg:px-12 xl:px-16">
            <div class="grid flex-1 items-center gap-5 lg:grid-cols-[1.15fr_0.78fr_0.9fr] xl:gap-6">
                <div class="flex min-h-[520px] flex-col items-center justify-center text-center">
                    <img
                        src="{{ asset('images/logo-loteria.png') }}"
                        alt="{{ config('app.name', 'Loteria Automatica') }}"
                        class="h-auto w-[330px] max-w-[78vw] sm:w-[430px] lg:w-[500px]"
                    >

                    <div class="mt-9 grid w-full max-w-[640px] grid-cols-1 gap-5 sm:grid-cols-3">
                        <div class="login-feature">
                            <div class="login-feature-icon">
                                <span class="icon-paper-plane"></span>
                            </div>
                            <p class="mt-4 text-sm font-bold">Automatizaci&oacute;n</p>
                            <p class="mt-2 text-xs leading-5 text-[#465f8e]">{{ __('Solicitudes por Telegram y Web') }}</p>
                        </div>

                        <div class="login-feature sm:border-x sm:border-[#dce6f5]">
                            <div class="login-feature-icon">
                                <span class="icon-shield"></span>
                            </div>
                            <p class="mt-4 text-sm font-bold">{{ __('Control') }}</p>
                            <p class="mt-2 text-xs leading-5 text-[#465f8e]">L&iacute;mites, restricciones y validaciones</p>
                        </div>

                        <div class="login-feature">
                            <div class="login-feature-icon">
                                <span class="icon-bars"></span>
                            </div>
                            <p class="mt-4 text-sm font-bold">Operaci&oacute;n en vivo</p>
                            <p class="mt-2 text-xs leading-5 text-[#465f8e]">{{ __('Monitorea tus sorteos en tiempo real') }}</p>
                        </div>
                    </div>

                    <div class="mt-7 inline-flex items-center gap-3 rounded-2xl border border-[#d7e2f2] bg-white/40 px-7 py-3 text-sm text-[#40567f] shadow-[0_12px_30px_rgba(30,69,129,0.08)] backdrop-blur-xl">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_0_6px_rgba(16,185,129,0.12)]"></span>
                        {{ __('Sistema operativo y monitoreado 24/7') }}
                    </div>
                </div>

                <div class="login-panel px-7 py-8 sm:px-9 lg:min-h-[560px] lg:py-10">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[#e6f0ff] text-brand-primary shadow-inner">
                        <span class="icon-lock"></span>
                    </div>

                    <div class="mt-7">
                        <h1 class="text-3xl font-extrabold tracking-normal text-brand-navy">Iniciar sesi&oacute;n</h1>
                        <p class="mt-3 max-w-[320px] text-base leading-7 text-[#526994]">Bienvenido de nuevo, por favor inicia sesi&oacute;n para continuar.</p>
                    </div>

                    <x-auth-session-status class="mt-5 mb-5" :status="session('status')" />

                    <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5">
                        @csrf

                        <div>
                            <x-input-label for="email" class="text-[13px] font-bold text-[#314775]">Correo electr&oacute;nico</x-input-label>
                            <div class="relative mt-3">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#445d91]">
                                    <span class="icon-mail"></span>
                                </span>
                                <x-text-input id="email" class="block h-12 w-full rounded-xl border-[#cbd8ea] bg-white/75 pl-12 pr-4 text-sm shadow-[0_8px_20px_rgba(21,48,92,0.04)] placeholder:text-[#6f80a2]" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="ejemplo@correo.com" />
                            </div>
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="password" class="text-[13px] font-bold text-[#314775]">Contrase&ntilde;a</x-input-label>
                            <div class="relative mt-3">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#445d91]">
                                    <span class="icon-lock-small"></span>
                                </span>
                                <x-text-input id="password" class="block h-12 w-full rounded-xl border-[#cbd8ea] bg-white/75 px-12 text-sm tracking-[0.12em] shadow-[0_8px_20px_rgba(21,48,92,0.04)] placeholder:text-[#071f4d]" type="password" name="password" required autocomplete="current-password" placeholder="********" />
                                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[#445d91]">
                                    <span class="icon-eye"></span>
                                </span>
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label for="remember_me" class="inline-flex items-center">
                                <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-[#b8c7dd] bg-white/80 text-brand-primary shadow-sm focus:ring-brand-primary" name="remember">
                                <span class="ms-2 text-sm font-medium text-[#526994]">{{ __('Recordarme') }}</span>
                            </label>

                            @if (Route::has('password.request'))
                                <a class="text-sm font-semibold text-[#005fd3] underline-offset-4 hover:underline" href="{{ route('password.request') }}">
                                    &iquest;Olvidaste tu contrase&ntilde;a?
                                </a>
                            @endif
                        </div>

                        <button type="submit" class="login-submit">
                            <span>Iniciar sesi&oacute;n</span>
                            <span class="login-submit-icon">
                                <span class="icon-arrow-right"></span>
                            </span>
                        </button>
                    </form>

                    <p class="mt-7 text-center text-sm text-[#526994]">&iquest;Necesitas ayuda? <a class="font-semibold text-[#005fd3]" href="mailto:admin@example.com">{{ __('Contacta al administrador') }}</a></p>
                </div>

                <aside class="login-panel px-6 py-7 lg:min-h-[560px]">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#fff5df] text-brand-gold">
                            <span class="icon-broadcast"></span>
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-brand-navy">{{ __('Sorteos en vivo') }}</h2>
                            <p class="mt-1 text-sm font-medium text-[#526994]">{{ __('Estado actual de sorteos') }}</p>
                        </div>
                    </div>

                    <div class="mt-9 space-y-3">
                        <div class="login-draw-card">
                            <div class="login-draw-icon text-brand-gold"><span class="icon-sun"></span></div>
                            <div class="min-w-0 flex-1">
                                <p class="text-lg font-extrabold">12:00 md</p>
                                <p class="mt-1 text-sm text-[#526994]">{{ __('Sorteo del Medio Dia') }}</p>
                            </div>
                            <span class="login-status-open">{{ __('Abierto') }}</span>
                        </div>

                        <div class="login-draw-card">
                            <div class="login-draw-icon text-brand-gold"><span class="icon-sun"></span></div>
                            <div class="min-w-0 flex-1">
                                <p class="text-lg font-extrabold">2:00 pm</p>
                                <p class="mt-1 text-sm text-[#526994]">{{ __('Sorteo de la Tarde') }}</p>
                            </div>
                            <span class="login-status-open">{{ __('Abierto') }}</span>
                        </div>

                        <div class="login-draw-card">
                            <div class="login-draw-icon text-brand-gold"><span class="icon-sun"></span></div>
                            <div class="min-w-0 flex-1">
                                <p class="text-lg font-extrabold">5:00 pm</p>
                                <p class="mt-1 text-sm text-[#526994]">{{ __('Sorteo de la Noche') }}</p>
                            </div>
                            <span class="login-status-open">{{ __('Abierto') }}</span>
                        </div>

                        <div class="login-draw-card">
                            <div class="login-draw-icon bg-[#eaf2ff] text-[#0e65d8]"><span class="icon-moon"></span></div>
                            <div class="min-w-0 flex-1">
                                <p class="text-lg font-extrabold">7:00 pm</p>
                                <p class="mt-1 text-sm text-[#526994]">{{ __('Sorteo de la Noche 2') }}</p>
                            </div>
                            <span class="login-status-closed">{{ __('Cerrado') }}</span>
                        </div>
                    </div>

                    <a href="#" class="mt-8 inline-flex items-center gap-3 text-sm font-semibold text-[#005fd3] underline underline-offset-4">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#e8f1ff] no-underline">
                            <span class="icon-clock"></span>
                        </span>
                        {{ __('Ver calendario completo') }}
                        <span class="icon-chevron-right"></span>
                    </a>
                </aside>
            </div>

            <div class="login-stats">
                <div class="login-stat-item">
                    <div class="login-stat-icon bg-[#dceaff] text-[#005fd3]"><span class="icon-users"></span></div>
                    <div>
                        <p class="text-2xl font-extrabold">1,248</p>
                        <p class="text-sm text-[#526994]">{{ __('Solicitudes hoy') }}</p>
                    </div>
                </div>

                <div class="login-stat-item">
                    <div class="login-stat-icon bg-[#dcf7eb] text-[#059669]"><span class="icon-ticket"></span></div>
                    <div>
                        <p class="text-2xl font-extrabold">4</p>
                        <p class="text-sm text-[#526994]">{{ __('Sorteos abiertos') }}</p>
                    </div>
                </div>

                <div class="login-stat-item">
                    <div class="login-stat-icon bg-[#f0e7ff] text-[#7c3aed]"><span class="icon-shield"></span></div>
                    <div>
                        <p class="text-2xl font-extrabold">98</p>
                        <p class="text-sm text-[#526994]">N&uacute;meros monitoreados</p>
                    </div>
                </div>

                <div class="login-stat-item border-0">
                    <div class="login-stat-icon bg-[#fff2d6] text-brand-gold"><span class="icon-coins"></span></div>
                    <div>
                        <p class="text-2xl font-extrabold">$48,750</p>
                        <p class="text-sm text-[#526994]">{{ __('Monto total confirmado') }}</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>
