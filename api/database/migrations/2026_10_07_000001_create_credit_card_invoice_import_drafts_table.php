<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_invoice_import_drafts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('credit_card_id');
            $table->string('reference_month', 7);
            $table->date('paid_date')->nullable();
            $table->uuid('bank_account_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->uuid('cost_center_id')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedTinyInteger('step')->default(2);
            $table->string('source_filename')->nullable();
            $table->json('items');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'credit_card_id', 'reference_month'],
                'cc_invoice_import_drafts_unique_period',
            );
            $table->index(['tenant_id', 'credit_card_id', 'updated_at'], 'cc_invoice_import_drafts_card_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_invoice_import_drafts');
    }
};
