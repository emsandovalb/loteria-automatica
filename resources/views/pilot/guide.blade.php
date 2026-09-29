<x-app-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('Operator Guide') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Short guide for a local pilot operator.') }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <ul class="space-y-3 text-sm text-slate-700">
                <li><strong>{{ __('Simulator:') }}</strong> {{ __('creates a local incoming message and request for testing.') }}</li>
                <li><strong>{{ __('Incoming messages:') }}</strong> {{ __('every received text is stored here first.') }}</li>
                <li><strong>{{ __('Requests:') }}</strong> {{ __('review items created from messages.') }}</li>
                <li><strong>{{ __('Pending:') }}</strong> {{ __('looks clear enough to review manually.') }}</li>
                <li><strong>{{ __('Needs review:') }}</strong> {{ __('message is unclear or mixed and needs manual review.') }}</li>
                <li><strong>{{ __('Confirm:') }}</strong> {{ __('accept a request after checking it.') }}</li>
                <li><strong>{{ __('Reject:') }}</strong> {{ __('deny an invalid request and add a reason.') }}</li>
                <li><strong>{{ __('Close the day:') }}</strong> {{ __('snapshot branch totals at the end of the day.') }}</li>
                <li><strong>{{ __('Export CSV:') }}</strong> {{ __('download the closure request list for records.') }}</li>
            </ul>
        </div>
    </div>
</x-app-layout>
