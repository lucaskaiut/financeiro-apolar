<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('cost_center_id')->nullable();
            $table->date('date');
            $table->decimal('value', 15, 2)->default(0);
            $table->string('type')->default('credit');
            $table->text('description')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'cost_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
