<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Branches</h1>
                <p class="mt-1 text-sm text-slate-600">Visible branches for your current scope.</p>
            </div>
            <a href="{{ route('closures.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Open closures
            </a>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Channel</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Identifier</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($branches as $branch)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $branch->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $branch->channel_type }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $branch->channel_identifier ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $branch->status }}</td>
                            <td class="px-4 py-3 text-sm">
                                @can('create', [\App\Models\BranchDailyClosure::class, $branch])
                                    <a href="{{ route('closures.index', ['branch_id' => $branch->id, 'closure_date' => today()->toDateString()]) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-50">
                                        Close Day
                                    </a>
                                @else
                                    <span class="text-sm text-slate-400">Read only</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No branches available for this account.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>