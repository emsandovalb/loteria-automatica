<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-600">Operational overview for the current scope.</p>
            </div>
            <a href="{{ route('simulator.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Open simulator
            </a>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">Incoming messages today</div>
                <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalIncomingMessagesToday }}</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">Pending requests</div>
                <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalPendingRequests }}</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">Needs review</div>
                <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalNeedsReviewRequests }}</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">Confirmed today</div>
                <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalConfirmedRequestsToday }}</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">Totals by branch</h2>
                    <span class="text-xs text-slate-500">Visible scope</span>
                </div>
                <div class="mt-4 overflow-hidden rounded-md border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Branch</th>
                                <th class="px-3 py-2 text-left font-medium">Today</th>
                                <th class="px-3 py-2 text-left font-medium">Pending</th>
                                <th class="px-3 py-2 text-left font-medium">Needs review</th>
                                <th class="px-3 py-2 text-left font-medium">Confirmed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($branchTotals as $branch)
                                <tr>
                                    <td class="px-3 py-2 text-slate-900">{{ $branch->name }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $branch->requests_today_count }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $branch->pending_requests_count }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $branch->needs_review_requests_count }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $branch->confirmed_requests_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-center text-slate-500">No branches available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">Status totals</h2>
                    <span class="text-xs text-slate-500">Current scope</span>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="text-sm text-slate-500">Pending</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $statusCounts['pending'] }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="text-sm text-slate-500">Needs review</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $statusCounts['needs_review'] }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="text-sm text-slate-500">Confirmed</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $statusCounts['confirmed'] }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="text-sm text-slate-500">Rejected</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $statusCounts['rejected'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Top active branches today</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($topBranchesToday as $branch)
                        <div class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2">
                            <span class="text-sm text-slate-900">{{ $branch->name }}</span>
                            <span class="text-sm font-semibold text-slate-700">{{ $branch->requests_today_count }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No activity today.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Requests needing review</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($needsReviewRequests as $request)
                        <div class="rounded-md border border-slate-200 px-3 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-medium text-slate-900">{{ $request->branch?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $request->created_at?->format('Y-m-d H:i') }}</div>
                            </div>
                            <div class="mt-1 text-xs text-slate-600">{{ $request->customer?->phone ?? '-' }} · {{ $request->raw_text }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No requests need review.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Recent closures</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($recentClosures as $closure)
                        <div class="rounded-md border border-slate-200 px-3 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-medium text-slate-900">{{ $closure->branch?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $closure->closure_date?->format('Y-m-d') }}</div>
                            </div>
                            <div class="mt-1 text-xs text-slate-600">Closed by {{ $closure->closedByUser?->name ?? '-' }} · Requests {{ $closure->total_requests }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No closures recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Operational indicators</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="text-sm text-slate-500">Pending &gt; 24h</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $stalePendingRequestCount }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="text-sm text-slate-500">Needs review</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $totalNeedsReviewRequests }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="text-sm text-slate-500">Pending previous day</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $previousDayPendingCount }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Status guidance</h2>
                <p class="mt-2 text-sm text-slate-600">Pending, needs_review, and older unpaid items stay visible until an authorized user confirms or rejects them.</p>
            </div>
        </div>
    </div>
</x-app-layout>