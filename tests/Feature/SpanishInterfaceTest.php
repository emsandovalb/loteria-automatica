<?php

namespace Tests\Feature;

use App\Models\IntakeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpanishInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        app()->setLocale('es');
    }

    public function test_main_pages_render_in_spanish(): void
    {
        $owner = User::query()->where('email', 'owner@local.test')->firstOrFail();
        $request = IntakeRequest::query()->firstOrFail();

        $pages = [
            route('dashboard') => 'Panel',
            route('intake-requests.index') => 'Solicitudes',
            route('intake-requests.show', $request) => 'Mensajes con el cliente',
            route('numbers.index') => 'Actualizar tablero',
            route('limits.index') => 'Límites',
            route('draws.index') => 'Sorteos',
            route('branches.index') => 'Sucursales',
            route('closures.index') => 'Cierres',
            route('incoming-messages.index') => 'Mensajes recibidos',
            route('simulator.index') => 'Simulador de recepción',
            route('results.index') => 'Resultados',
            route('payouts.index') => 'Pago de premios',
            route('settlement.index') => 'Liquidación diaria',
            route('users.index') => 'Usuarios',
            route('users.create') => 'Nuevo usuario',
        ];

        foreach ($pages as $url => $expected) {
            $this->actingAs($owner)->get($url)->assertOk()->assertSee($expected);
        }
    }

    public function test_statuses_and_system_notes_are_translated(): void
    {
        $owner = User::query()->where('email', 'owner@local.test')->firstOrFail();
        $request = IntakeRequest::query()->where('status', IntakeRequest::STATUS_NEEDS_REVIEW)->firstOrFail();
        $request->update(['notes' => 'Draw schedule is required. Manual review required.']);

        $this->actingAs($owner)->get(route('intake-requests.show', $request))
            ->assertOk()
            ->assertSee('en revisión')
            ->assertSee('Falta indicar el sorteo/horario. Requiere revisión manual.');
    }

    public function test_validation_messages_are_in_spanish(): void
    {
        $owner = User::query()->where('email', 'owner@local.test')->firstOrFail();
        $request = IntakeRequest::query()->where('status', IntakeRequest::STATUS_NEEDS_REVIEW)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('intake-requests.reject', $request), ['rejection_reason' => ''])
            ->assertSessionHasErrors(['rejection_reason' => 'El campo motivo del rechazo es obligatorio.']);
    }
}
