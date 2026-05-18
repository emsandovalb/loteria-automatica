<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Closures</h1>
                <p class="mt-1 text-sm text-slate-600">Daily branch closure snapshots and operational filters.</p>
            </div>
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif
        </div>

        @if (! auth()->user()->isViewer())
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <form method="POST" action="{{ route('closures.store') }}" class="grid gap-4 lg:grid-cols-4">
                    @csrf
                    <div>
                        <label for="branch_id" class="block text-sm font-medium text-slate-700">Branch</label>
                        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">Select a branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="closure_date" class="block text-sm font-medium text-slate-700">Closure date</label>
                        <input id="closure_date" name="closure_date" type="date" value="{{ request('closure_date', today()->toDateString()) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('closure_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="lg:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-slate-700">Notes</label>
                        <input id="notes" name="notes" type="text" value="{{ old('notes') }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Optional operational notes">
                        @error('notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="lg:col-span-4 flex justify-end">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Close Day
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                Viewer access is read-only. Closure actions are disabled.
            </div>
        @endif

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('closures.index') }}" class="grid gap-4 lg:grid-cols-4">
                <div>
                    <label for="filter_branch_id" class="block text-sm font-medium text-slate-700">Branch</label>
                    <select id="filter_branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">All branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter_closure_date" class="block text-sm font-medium text-slate-700">Date</label>
                    <input id="filter_closure_date" name="closure_date" type="date" value="{{ request('closure_date') }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
                <div>
                    <label for="filter_closed_by" class="block text-sm font-medium text-slate-700">User</label>
                    <select id="filter_closed_by" name="closed_by" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">All users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(request('closed_by') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Apply</button>
                    <a href="{{ route('closures.index') }}" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Closed by</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Requests</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Confirmed</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Rejected</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($closures as $closure)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->closure_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $closure->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $closure->closedByUser?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_requests }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_confirmed }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_rejected }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_pending }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_amount_confirmed }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    @can('view', $closure)
                                        <a href="{{ route('closures.show', $closure) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-50">View</a>
                                        <a href="{{ route('closures.export', $closure) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Export CSV</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @if ($closure->notes)
                            <tr class="bg-slate-50">
                                <td colspan="9" class="px-4 py-3 text-sm text-slate-600">{{ $closure->notes }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">No closures found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $closures->links() }}
    </div>
</x-app-layout>
