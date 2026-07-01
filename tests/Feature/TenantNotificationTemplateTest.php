<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Notifications\Actions\DeleteNotificationTemplateAction;
use App\Modules\Tenant\Notifications\Actions\UpsertNotificationTemplateAction;
use App\Modules\Tenant\Notifications\DTOs\UpsertNotificationTemplateData;
use App\Modules\Tenant\Notifications\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'notif-template-test',
        'name' => 'Template Test',
        'email' => 'template@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('creates a notification template', function () {
    $action = app(UpsertNotificationTemplateAction::class);

    $data = new UpsertNotificationTemplateData(
        key: 'user.invited',
        channel: 'email',
        subject: 'You are invited to {{tenant}}',
        body: '<p>Hello {{name}}, welcome!</p>',
    );

    $template = $action->execute($data);

    expect($template)->toBeInstanceOf(NotificationTemplate::class);
    expect($template->key)->toBe('user.invited');
    expect($template->channel)->toBe('email');
    expect($template->subject)->toBe('You are invited to {{tenant}}');
});

it('updates existing template on duplicate key+channel', function () {
    $action = app(UpsertNotificationTemplateAction::class);

    $action->execute(new UpsertNotificationTemplateData(
        key: 'user.invited',
        channel: 'email',
        subject: 'Original',
    ));

    $action->execute(new UpsertNotificationTemplateData(
        key: 'user.invited',
        channel: 'email',
        subject: 'Updated',
        body: 'New body',
    ));

    expect(NotificationTemplate::count())->toBe(1);
    expect(NotificationTemplate::first()->subject)->toBe('Updated');
});

it('creates in-app and email templates separately', function () {
    $action = app(UpsertNotificationTemplateAction::class);

    $action->execute(new UpsertNotificationTemplateData(
        key: 'user.invited',
        channel: 'email',
        subject: 'Email version',
    ));

    $action->execute(new UpsertNotificationTemplateData(
        key: 'user.invited',
        channel: 'in-app',
        body: 'In-app version',
    ));

    expect(NotificationTemplate::count())->toBe(2);
});

it('deletes a notification template', function () {
    $template = NotificationTemplate::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'key' => 'test.key',
        'channel' => 'email',
    ]);

    $action = app(DeleteNotificationTemplateAction::class);
    $action->execute($template->id);

    expect(NotificationTemplate::count())->toBe(0);
});

it('scopes templates to current tenant', function () {
    $otherTenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'other-tenant',
        'name' => 'Other',
        'email' => 'other@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($otherTenant);

    NotificationTemplate::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $otherTenant->id,
        'key' => 'other.key',
        'channel' => 'email',
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenant);

    expect(NotificationTemplate::count())->toBe(0);
});
