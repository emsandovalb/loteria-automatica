<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Requests</h1>
                <p class="mt-1 text-sm text-slate-600">Review queue for the current scope.</p>
            </div>

            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('intake-requests.index') }}" class="grid gap-4 lg:grid-cols-3 xl:grid-cols-6">
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">All</option>
                        @foreach ([\App\Models\IntakeRequest::STATUS_PENDING, \App\Models\IntakeRequest::STATUS_NEEDS_REVIEW, \App\Models\IntakeRequest::STATUS_CONFIRMED, \App\Models\IntakeRequest::STATUS_REJECTED] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-slate-700">Branch</label>
                    <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">All</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="draw_id" class="block text-sm font-medium text-slate-700">Draw</label>
                    <select id="draw_id" name="draw_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">All</option>
                        @foreach ($draws as $draw)
                            <option value="{{ $draw->id }}" @selected((string) ($filters['draw_id'] ?? '') === (string) $draw->id)>{{ $draw->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-sm font-medium text-slate-700">Date from</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-slate-700">Date to</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-slate-700">Customer phone</label>
                    <input id="customer_phone" name="customer_phone" type="text" value="{{ $filters['customer_phone'] ?? '' }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="+50255510001">
                </div>
                <div>
                    <label for="detected_number" class="block text-sm font-medium text-slate-700">Detected number</label>
                    <input id="detected_number" name="detected_number" type="text" value="{{ $filters['detected_number'] ?? '' }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="28">
                </div>
                <div class="flex items-end gap-2 xl:col-span-6">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Apply</button>
                    <a href="{{ route('intake-requests.index') }}" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Created</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Detected #</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Draw</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Indicators</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($requests as $request)
                        @php
                            $isPending = $request->status === \App\Models\IntakeRequest::STATUS_PENDING;
                            $isNeedsReview = $request->status === \App\Models\IntakeRequest::STATUS_NEEDS_REVIEW;
                            $isStale = $isPending && $request->created_at && $request->created_at->lt(now()->subHours($staleThresholdHours));
                            $isPreviousDay = $isPending && $request->created_at && $request->created_at->isBefore(today());
                        @endphp
                        <tr class="{{ $isNeedsReview ? 'bg-amber-50/40' : ($isStale ? 'bg-red-50/40' : '') }}">
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <div>{{ $request->created_at?->format('Y-m-d H:i') }}</div>
                                @if ($isPreviousDay)
                                    <div class="mt-1 text-xs font-semibold text-red-700">Previous day</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $request->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $request->detected_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if ($request->draw)
                                    <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800">
                                        {{ $request->draw->name }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $request->detected_amount ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $request->status === \App\Models\IntakeRequest::STATUS_CONFIRMED ? 'bg-emerald-100 text-emerald-800' : ($request->status === \App\Models\IntakeRequest::STATUS_REJECTED ? 'bg-red-100 text-red-800' : ($request->status === \App\Models\IntakeRequest::STATUS_NEEDS_REVIEW ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')) }}">
                                    {{ str_replace('_', ' ', $request->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    @if ($isNeedsReview)
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Needs review</span>
                                    @endif
                                    @if ($isStale)
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Pending &gt; {{ $staleThresholdHours }}h</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $request->notes ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('intake-requests.show', $request) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-50">View</a>
                                    <a href="{{ route('intake-requests.edit', $request) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-50">View/Edit</a>

                                    @can('confirm', $request)
                                        <form method="POST" action="{{ route('intake-requests.confirm', $request) }}">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-white hover:bg-slate-800">Confirm</button>
                                        </form>
                                        <details class="group">
                                            <summary class="cursor-pointer list-none rounded-md border border-red-200 px-3 py-1.5 text-red-700 hover:bg-red-50">Reject</summary>
                                            <form method="POST" action="{{ route('intake-requests.reject', $request) }}" class="mt-2 space-y-2 rounded-md border border-red-200 bg-red-50 p-3">
                                                @csrf
                                                <textarea name="rejection_reason" rows="2" class="block w-full rounded-md border-red-200 shadow-sm focus:border-red-400 focus:ring-red-400" placeholder="Rejection reason"></textarea>
                                                <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-white hover:bg-red-500">Submit reject</button>
                                            </form>
                                        </details>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">No requests available for this account.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $requests->links() }}
    </div>
</x-app-layout>
