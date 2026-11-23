<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_clicks', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliate_clicks', 'sub_id')) {
                $table->string('sub_id', 100)->nullable()->after('session_id')->index();
            }
            if (! Schema::hasColumn('affiliate_clicks', 'network')) {
                $table->string('network', 30)->nullable()->after('sub_id')->index();
            }
            if (! Schema::hasColumn('affiliate_clicks', 'status')) {
                $table->string('status', 20)->default('pending')->after('commission_amount');
                // pending | converted | paid | rejected
            }
            if (! Schema::hasColumn('affiliate_clicks', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('affiliate_clicks', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('converted_at');
            }
            if (! Schema::hasColumn('affiliate_clicks', 'external_order_id')) {
                $table->string('external_order_id', 100)->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('affiliate_clicks', 'webhook_payload')) {
                $table->json('webhook_payload')->nullable()->after('external_order_id');
            }
        });

        Schema::table('affiliate_clicks', function (Blueprint $table) {
            $table->index(['network', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_clicks', function (Blueprint $table) {
            $table->dropIndex(['network', 'status']);
            $table->dropColumn([
                'sub_id', 'network', 'status', 'converted_at', 'paid_at',
                'external_order_id', 'webhook_payload',
            ]);
        });
    }
};