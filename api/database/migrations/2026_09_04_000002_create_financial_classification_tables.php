<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name');
                $table->string('status')->default('active');
                $table->softDeletes();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('cost_centers')) {
            Schema::create('cost_centers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('financial_cost_centers_uuid_unique');
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name');
                $table->string('status')->default('active');
                $table->softDeletes();
                $table->timestamps();

                $table->index(['tenant_id', 'status'], 'financial_cost_centers_tenant_status_idx');
            });
        }

        if (! Schema::hasTable('credit_cards')) {
            Schema::create('credit_cards', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name');
                $table->string('institution')->nullable();
                $table->decimal('limit', 15, 2)->nullable();
                $table->unsignedTinyInteger('closing_day')->nullable();
                $table->unsignedTinyInteger('due_day')->nullable();
                $table->uuid('bank_account_id')->nullable();
                $table->string('status')->default('active');
                $table->softDeletes();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('credit_card_invoices')) {
            Schema::create('credit_card_invoices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->uuid('credit_card_id');
                $table->string('reference_month', 7);
                $table->date('closing_date');
                $table->date('due_date');
                $table->decimal('total_value', 15, 2)->default(0);
                $table->string('status')->default('open');
                $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tenant_id', 'credit_card_id', 'reference_month'], 'credit_card_invoices_unique_period');
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('account_allocations')) {
            Schema::create('account_allocations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('financial_accounts')->cascadeOnDelete();
                $table->uuid('cost_center_id')->nullable();
                $table->uuid('company_id')->nullable();
                $table->uuid('category_id')->nullable();
                $table->uuid('subcategory_id')->nullable();
                $table->decimal('value', 15, 2);
                $table->decimal('percentage', 8, 4)->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'account_id']);
                $table->index(['tenant_id', 'cost_center_id']);
                $table->index(['tenant_id', 'company_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_allocations');
        Schema::dropIfExists('credit_card_invoices');
        Schema::dropIfExists('credit_cards');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('companies');
    }
};
