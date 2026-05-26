<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Draw settings</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Draws</h1>
                <p class="mt-1 text-sm text-slate-600">Review draw schedules and intake closing rules.</p>
            </div>
            @if (session('status'))
                <div class="rounded-2xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                    {{ session('status') }}
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Draw time</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Close time</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cutoff</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Intake</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($draws as $draw)
                        @php
                            $closingReason = $draw->closingReason();
                            $badgeClass = match ($closingReason) {
                                'inactive' => 'bg-slate-100 text-slate-700',
                                'manually_closed' => 'bg-amber-100 text-amber-800',
                                'closed_by_time', 'closed_by_cutoff' => 'bg-red-100 text-red-800',
                                default => 'bg-green-100 text-green-800',
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-brand-navy">{{ $draw->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $draw->draw_time }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $draw->close_time ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $draw->cutoff_minutes_before !== null ? $draw->cutoff_minutes_before . ' min' : '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="brand-badge {{ $draw->isOpenForIntake() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $draw->intakeStatusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="brand-badge {{ $badgeClass }}">
                                    {{ $draw->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @can('update', $draw)
                                    <a href="{{ route('draws.edit', $draw) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Edit</a>
                                @else
                                    <span class="text-sm text-slate-400">View only</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No draws available for this account.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
