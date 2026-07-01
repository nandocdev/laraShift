<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50);
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['type', 'version']);
            $table->index('type');
            $table->foreign('created_by')->references('id')->on('central_users')->onDelete('set null');
        });

        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('legal_document_id');
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at');

            $table->index('legal_document_id');
            $table->foreign('legal_document_id')->references('id')->on('legal_documents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
    }
};
