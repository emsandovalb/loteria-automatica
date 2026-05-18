<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Limits</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Manage number limits by branch, draw, and number.
                </p>
            </div>

            @if ($canManageLimits)
                <a href="{{ route('limits.create', request()->only(['branch_id', 'draw_id'])) }}" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Create limit
                </a>
            @endif
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('limits.index') }}" class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-slate-700">Branch</label>
                    <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">All branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="draw_id" class="block text-sm font-medium text-slate-700">Draw</label>
                    <select id="draw_id" name="draw_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">All draws</option>
                        @foreach ($draws as $draw)
                            <option value="{{ $draw->id }}" @selected((string) ($filters['draw_id'] ?? '') === (string) $draw->id)>{{ $draw->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="number" class="block text-sm font-medium text-slate-700">Number</label>
                    <input id="number" name="number" value="{{ $filters['number'] ?? '' }}" type="text" maxlength="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="00">
                </div>

                <div class="flex items-end gap-2 md:col-span-3 xl:col-span-1">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Apply</button>
                    <a href="{{ route('limits.index') }}" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </div>

        @if (! $canManageLimits)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                Read-only access. Limits can be viewed but not edited from this account.
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Draw</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Number</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Max amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Updated</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($limits as $limit)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $limit->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $limit->draw?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $limit->number }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">₡{{ number_format((float) $limit->max_amount, 2, '.', ',') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $limit->updated_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if ($canManageLimits)
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('limits.edit', $limit) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Edit</a>
                                        <form method="POST" action="{{ route('limits.delete', $limit) }}" onsubmit="return confirm('Delete this limit?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-md border border-rose-200 px-3 py-1.5 text-rose-700 hover:bg-rose-50">Delete</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-slate-400">View only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No limits found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $limits->links() }}
    </div>
</x-app-layout>
