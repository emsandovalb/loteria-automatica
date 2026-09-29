<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">{{ __('Team') }}</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ __('Users') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Create sellers and staff, assign their branch and deactivate accounts that should no longer log in.') }}</p>
            </div>
            @can('create', \App\Models\User::class)
                <a href="{{ route('users.create') }}" class="brand-btn-primary">{{ __('New user') }}</a>
            @endcan
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-x-auto rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Email') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Role') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Branch') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach ($users as $user)
                        <tr class="{{ $user->is_active ? '' : 'bg-slate-50 text-slate-400' }}">
                            <td class="px-4 py-3 text-sm font-medium text-brand-navy">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ __($user->role) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $user->branch?->name ?? __('All branches') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="brand-badge {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $user->is_active ? __('Active') : __('Deactivated') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @can('update', $user)
                                    <a href="{{ route('users.edit', $user) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">{{ __('Edit') }}</a>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
