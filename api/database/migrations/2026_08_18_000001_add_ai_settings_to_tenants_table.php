<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(false)->after('domain');
            $table->string('ai_endpoint')->nullable()->after('ai_enabled');
            $table->text('ai_api_key')->nullable()->after('ai_endpoint');
            $table->string('ai_model')->nullable()->after('ai_api_key');
            $table->decimal('ai_temperature', 4, 2)->nullable()->default(0.20)->after('ai_model');
            $table->unsignedInteger('ai_max_tokens')->nullable()->after('ai_temperature');
            $table->text('ai_system_prompt')->nullable()->after('ai_max_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'ai_enabled',
                'ai_endpoint',
                'ai_api_key',
                'ai_model',
                'ai_temperature',
                'ai_max_tokens',
                'ai_system_prompt',
            ]);
        });
    }
};
