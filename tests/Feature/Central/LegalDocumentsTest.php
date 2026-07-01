<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Marketing\Actions\PublishLegalDocumentAction;
use App\Modules\Central\Marketing\Actions\UpsertLegalDocumentAction;
use App\Modules\Central\Marketing\Livewire\ManageLegalDocuments;
use App\Modules\Central\Marketing\Models\LegalDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Legal Admin',
        'email' => 'legal@admin.com',
        'password' => 'password',
        'is_global_admin' => true,
    ]);

    $this->actingAs($this->admin, 'central');
});

test('creates a new legal document version', function () {
    $action = app(UpsertLegalDocumentAction::class);

    $doc = $action->execute('terms', 'Terms of Service v1', '<p>Terms content</p>', false, $this->admin->id);

    expect($doc)->toBeInstanceOf(LegalDocument::class);
    expect($doc->type)->toBe('terms');
    expect($doc->version)->toBe(1);
    expect($doc->content)->toBe('<p>Terms content</p>');
});

test('increments version on each update', function () {
    $action = app(UpsertLegalDocumentAction::class);

    $action->execute('privacy', 'Privacy v1', '<p>v1</p>', false, $this->admin->id);
    $v2 = $action->execute('privacy', 'Privacy v2', '<p>v2</p>', false, $this->admin->id);

    expect($v2->version)->toBe(2);
});

test('preserves previous version in history', function () {
    $action = app(UpsertLegalDocumentAction::class);

    $v1 = $action->execute('terms', 'Terms v1', '<p>Version 1</p>', false, $this->admin->id);
    $action->execute('terms', 'Terms v2', '<p>Version 2</p>', false, $this->admin->id);

    $history = LegalDocument::where('type', 'terms')->latest('version')->first()->versions;

    expect($history)->toHaveCount(1);
    expect($history->first()->content)->toBe('<p>Version 1</p>');
});

test('publishes a document and unpublishes others of same type', function () {
    $action = app(UpsertLegalDocumentAction::class);
    $publishAction = app(PublishLegalDocumentAction::class);

    $v1 = $action->execute('terms', 'Terms v1', '<p>v1</p>', true, $this->admin->id);
    $v2 = $action->execute('terms', 'Terms v2', '<p>v2</p>', false, $this->admin->id);

    expect($v1->fresh()->is_published)->toBeTrue();
    expect($v2->fresh()->is_published)->toBeFalse();

    $publishAction->execute($v2->id);

    expect($v1->fresh()->is_published)->toBeFalse();
    expect($v2->fresh()->is_published)->toBeTrue();
});

test('renders legal documents livewire component', function () {
    Livewire::test(ManageLegalDocuments::class)
        ->assertStatus(200);
});
