<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ __('Request Detail') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Full audit trail for this request.') }}</p>
            </div>
            <a href="{{ route('intake-requests.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                {{ __('Back to requests') }}
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Status') }}</div>
                            <div class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $request->status === \App\Models\IntakeRequest::STATUS_CONFIRMED ? 'bg-emerald-100 text-emerald-800' : ($request->status === \App\Models\IntakeRequest::STATUS_REJECTED ? 'bg-red-100 text-red-800' : ($request->status === \App\Models\IntakeRequest::STATUS_NEEDS_REVIEW ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')) }}">
                                {{ __(str_replace('_', ' ', $request->status)) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Branch') }}</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->branch?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Draw') }}</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->draw?->name ?? '-' }}</div>
                            @if ($request->draw)
                                <div class="mt-2">
                                    <span class="brand-badge {{ $request->draw->isOpenForIntake() ? 'bg-green-100 text-green-800' : ($request->draw->closingReason() === 'manually_closed' ? 'bg-amber-100 text-amber-800' : ($request->draw->closingReason() === 'inactive' ? 'bg-slate-100 text-slate-700' : 'bg-red-100 text-red-800')) }}">
                                        {{ $request->draw->intakeStatusLabel() }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Customer') }}</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->customer?->name ?? '-' }}</div>
                            <div class="text-sm text-slate-600">{{ $request->customer?->phone ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Raw message') }}</div>
                            <div class="mt-1 whitespace-pre-wrap text-sm text-slate-900">{{ $request->incomingMessage?->raw_text ?? $request->raw_text }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Incoming message ID') }}</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->incomingMessage?->id ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Detected number') }}</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->detected_number ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Detected amount') }}</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->detected_amount ?? '-' }}</div>
                            @if ($request->reventado_amount)
                                <div class="text-sm text-slate-600">{{ __('Reventado') }} ₡{{ $request->reventado_amount }}</div>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <div class="text-sm text-slate-500">{{ __('Notes') }}</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->notes ? __($request->notes) : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Created') }}</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->created_at?->format('Y-m-d H:i') }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Updated') }}</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->updated_at?->format('Y-m-d H:i') }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Confirmed at') }}</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">{{ __('Rejected at') }}</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->rejected_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <div class="text-sm text-slate-500">{{ __('Generated response') }}</div>
                            <div class="mt-1 whitespace-pre-wrap text-sm text-slate-900">{{ $request->incomingMessage?->response?->response_text ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Customer messages') }}</h2>
                        @if ($request->isAwaitingCustomerReply())
                            <span class="brand-badge bg-sky-100 text-sky-800">{{ __('Awaiting customer reply') }}</span>
                        @endif
                    </div>

                    @can('update', $request)
                        @if ($canMessageCustomer)
                            <form method="POST" action="{{ route('intake-requests.clarify', $request) }}" class="mt-4 space-y-2">
                                @csrf
                                <label for="question" class="block text-sm font-medium text-slate-700">{{ __('Ask the customer') }}</label>
                                <textarea id="question" name="question" rows="3" maxlength="1000" class="brand-input block w-full rounded-xl text-sm" placeholder="{{ __('Ej: ¿Qué número quieres jugar y por cuánto?') }}">{{ old('question') }}</textarea>
                                @error('question')
                                    <div class="text-sm text-brand-danger">{{ $message }}</div>
                                @enderror
                                <button type="submit" class="brand-btn-primary px-3 py-1.5 text-xs">{{ __('Send question') }}</button>
                            </form>
                        @else
                            <p class="mt-4 text-sm text-slate-500">{{ __('This request was created manually, so there is no customer chat to write to.') }}</p>
                        @endif
                    @endcan

                    <div class="mt-4 space-y-3">
                        @forelse ($request->outgoingMessages->sortByDesc('created_at') as $outgoing)
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-xs font-semibold text-slate-900">{{ __(str_replace('_', ' ', $outgoing->message_type)) }}</div>
                                    <span class="brand-badge {{ match ($outgoing->status) {
                                        \App\Models\OutgoingMessage::STATUS_SENT => 'bg-green-100 text-green-800',
                                        \App\Models\OutgoingMessage::STATUS_FAILED => 'bg-red-100 text-red-800',
                                        \App\Models\OutgoingMessage::STATUS_NOT_SENT => 'bg-slate-100 text-slate-700',
                                        default => 'bg-amber-100 text-amber-800',
                                    } }}">{{ __(str_replace('_', ' ', $outgoing->status)) }}</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ $outgoing->created_at?->format('Y-m-d H:i') }} · {{ $outgoing->user?->name ?? __('System') }} · {{ __($outgoing->channel_type) }}</div>
                                <div class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ $outgoing->text }}</div>
                                @if ($outgoing->error)
                                    <div class="mt-2 text-xs text-brand-danger">{{ $outgoing->error }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">{{ __('No messages sent to the customer yet.') }}</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Audit timeline') }}</h2>
                    <div class="mt-4 space-y-4">
                        @forelse ($request->events as $event)
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-sm font-semibold text-slate-900">{{ __(str_replace('_', ' ', $event->event_type)) }}</div>
                                    <div class="text-xs text-slate-500">{{ $event->created_at?->format('Y-m-d H:i') }}</div>
                                </div>
                                <div class="mt-1 text-xs text-slate-600">{{ $event->user?->name ?? __('System') }}</div>
                                @if ($event->notes)
                                    <div class="mt-2 text-sm text-slate-700">{{ __($event->notes) }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">{{ __('No audit events recorded yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
