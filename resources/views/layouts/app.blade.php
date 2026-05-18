<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @php($hasFrontendAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @if ($hasFrontendAssets)
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased bg-slate-100">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            <div class="flex min-h-screen">
                <aside
                    class="fixed inset-y-0 left-0 z-40 w-72 transform bg-slate-900 text-slate-100 transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-0"
                    :class="{ '-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen }"
                >
                    <div class="flex h-16 items-center justify-between border-b border-slate-800 px-6">
                        <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight">
                            Loteria Automatica
                        </a>
                        <button class="lg:hidden text-slate-300" @click="sidebarOpen = false">
                            ×
                        </button>
                    </div>

                    <nav class="space-y-1 px-3 py-4">
                        <a href="{{ route('dashboard') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('simulator.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('simulator.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Simulator
                        </a>
                        <a href="{{ route('numbers.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('numbers.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Numbers
                        </a>
                        <a href="{{ route('limits.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('limits.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Limits
                        </a>
                        <a href="{{ route('branches.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('branches.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Branches
                        </a>
                        <a href="{{ route('closures.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('closures.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Closures
                        </a>
                        <a href="{{ route('pilot.checklist') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('pilot.checklist') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Pilot Checklist
                        </a>
                        <a href="{{ route('pilot.script') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('pilot.script') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Pilot Script
                        </a>
                        <a href="{{ route('pilot.guide') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('pilot.guide') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Operator Guide
                        </a>
                        <a href="{{ route('incoming-messages.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('incoming-messages.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Incoming Messages
                        </a>
                        <a href="{{ route('intake-requests.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('intake-requests.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            Requests
                        </a>
                    </nav>

                    <div class="absolute bottom-0 left-0 right-0 border-t border-slate-800 px-6 py-4">
                        <div class="text-sm font-medium text-white">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-slate-400">{{ auth()->user()->email }}</div>
                        <form method="POST" action="{{ route('logout') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-slate-300 hover:text-white">
                                Logout
                            </button>
                        </form>
                    </div>
                </aside>

                <div class="flex min-w-0 flex-1 flex-col lg:ml-0">
                    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                        <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                            <button class="rounded-md border border-slate-200 px-3 py-2 text-sm lg:hidden" @click="sidebarOpen = true">
                                Menu
                            </button>
                            <div>
                                <div class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</div>
                                <div class="text-xs text-slate-500">{{ auth()->user()->role }}</div>
                            </div>
                            <div class="hidden sm:block text-sm text-slate-500">
                                {{ now()->format('M d, Y') }}
                            </div>
                        </div>
                    </header>

                    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
