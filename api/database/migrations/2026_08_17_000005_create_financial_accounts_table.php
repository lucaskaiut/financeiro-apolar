<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type')->default('payable');
            $table->string('description');
            $table->string('counterparty')->nullable();
            $table->uuid('cost_center_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->date('due_date');
            $table->date('expected_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->text('observation')->nullable();
            $table->string('status')->default('open');

            $table->uuid('installment_group_id')->nullable();
            $table->unsignedSmallInteger('installment_number')->nullable();
            $table->unsignedSmallInteger('installment_total')->nullable();

            $table->foreignId('recurrence_id')->nullable()->constrained('recurrences')->nullOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'cost_center_id']);
            $table->index(['tenant_id', 'category_id']);
            $table->index('installment_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
