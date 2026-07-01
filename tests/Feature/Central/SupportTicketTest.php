<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\AddTicketMessageAction;
use App\Modules\Central\Support\Actions\AssignTicketAction;
use App\Modules\Central\Support\Actions\CreateTicketAction;
use App\Modules\Central\Support\Actions\UpdateTicketStatusAction;
use App\Modules\Central\Support\DTOs\AddTicketMessageData;
use App\Modules\Central\Support\DTOs\CreateTicketData;
use App\Modules\Central\Support\Jobs\EscalateOverdueTicketsJob;
use App\Modules\Central\Support\Livewire\TicketList;
use App\Modules\Central\Support\Models\SupportTicket;
use App\Modules\Central\Support\Models\SupportTicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Support Admin',
        'email' => 'support@admin.com',
        'password' => 'password',
        'is_global_admin' => true,
    ]);

    $this->actingAs($this->admin, 'central');

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'support-tenant',
        'name' => 'Support Tenant',
        'email' => 'st@test.com',
        'plan_id' => 'free',
    ]);
});

it('creates a support ticket', function () {
    $action = app(CreateTicketAction::class);

    $data = new CreateTicketData(
        tenantId: $this->tenant->id,
        subject: 'Cannot access dashboard',
        description: 'User reports 500 error on login.',
        priority: 'high',
    );

    $ticket = $action->execute($data);

    expect($ticket)->toBeInstanceOf(SupportTicket::class);
    expect($ticket->subject)->toBe('Cannot access dashboard');
    expect($ticket->status)->toBe('open');
    expect($ticket->priority)->toBe('high');
    expect($ticket->sla_breach_at)->not->toBeNull();
});

it('sets SLA response time based on priority', function () {
    $action = app(CreateTicketAction::class);

    $critical = $action->execute(new CreateTicketData(
        tenantId: $this->tenant->id,
        subject: 'Critical issue',
        description: 'Production down.',
        priority: 'critical',
    ));
    expect(round(abs($critical->sla_breach_at->diffInMinutes(now()) / 60)))->toEqual(4);

    $low = $action->execute(new CreateTicketData(
        tenantId: $this->tenant->id,
        subject: 'Low priority',
        description: 'Minor cosmetic bug.',
        priority: 'low',
    ));
    expect(round(abs($low->sla_breach_at->diffInMinutes(now()) / 60)))->toEqual(48);
});

it('updates ticket status', function () {
    $ticket = SupportTicket::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'subject' => 'Test ticket',
        'description' => 'Test description',
        'status' => 'open',
        'priority' => 'medium',
        'created_by' => $this->admin->id,
    ]);

    $action = app(UpdateTicketStatusAction::class);
    $updated = $action->execute($ticket, 'resolved');

    expect($updated->status)->toBe('resolved');
    expect($updated->resolved_at)->not->toBeNull();
});

it('assigns ticket to an agent', function () {
    $agent = CentralUser::create([
        'name' => 'Agent User',
        'email' => 'agent@admin.com',
        'password' => 'password',
    ]);

    $ticket = SupportTicket::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'subject' => 'Assign test',
        'description' => 'Who should handle this?',
        'status' => 'open',
        'priority' => 'medium',
        'created_by' => $this->admin->id,
    ]);

    $action = app(AssignTicketAction::class);
    $updated = $action->execute($ticket, new \App\Modules\Central\Support\DTOs\UpdateTicketData(assignedTo: $agent->id));

    expect($updated->assigned_to)->toBe($agent->id);
    expect($updated->status)->toBe('in_progress');
});

it('adds messages to a ticket', function () {
    $ticket = SupportTicket::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'subject' => 'Message test',
        'description' => 'Testing messages',
        'status' => 'open',
        'priority' => 'medium',
        'created_by' => $this->admin->id,
    ]);

    $action = app(AddTicketMessageAction::class);

    $message = $action->execute($ticket, new AddTicketMessageData(
        content: 'I have investigated the issue.',
        isInternal: true,
    ));

    expect($message)->toBeInstanceOf(SupportTicketMessage::class);
    expect($message->content)->toBe('I have investigated the issue.');
    expect($message->is_internal)->toBeTrue();
    expect($message->author_id)->toBe($this->admin->id);
});

it('escalates overdue tickets via job', function () {
    SupportTicket::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'subject' => 'Overdue ticket',
        'description' => 'Should be escalated',
        'status' => 'open',
        'priority' => 'medium',
        'sla_breach_at' => now()->subHour(),
        'created_by' => $this->admin->id,
    ]);

    SupportTicket::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'subject' => 'On time ticket',
        'description' => 'Should NOT be escalated',
        'status' => 'open',
        'priority' => 'medium',
        'sla_breach_at' => now()->addDay(),
        'created_by' => $this->admin->id,
    ]);

    $job = new EscalateOverdueTicketsJob;
    $job->handle();

    expect(SupportTicket::where('status', 'escalated')->count())->toBe(1);
    expect(SupportTicket::where('subject', 'Overdue ticket')->first()->status)->toBe('escalated');
    expect(SupportTicket::where('subject', 'On time ticket')->first()->status)->toBe('open');
});

it('prevents adding messages to closed tickets', function () {
    $ticket = SupportTicket::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'subject' => 'Closed ticket',
        'description' => 'Already resolved',
        'status' => 'closed',
        'priority' => 'medium',
        'created_by' => $this->admin->id,
    ]);

    $action = app(AddTicketMessageAction::class);

    expect(fn () => $action->execute($ticket, new AddTicketMessageData(
        content: 'New message on closed ticket',
    )))->toThrow(RuntimeException::class);
});

it('registers ticket livewire components', function () {
    Livewire::test(TicketList::class)
        ->assertStatus(200);
});
