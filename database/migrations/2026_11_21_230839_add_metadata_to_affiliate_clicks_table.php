<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_clicks', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliate_clicks', 'referer')) {
                $table->string('referer', 500)->nullable()->after('user_agent');
            }
            if (! Schema::hasColumn('affiliate_clicks', 'session_id')) {
                $table->string('session_id', 100)->nullable()->after('referer');
            }
            if (! Schema::hasColumn('affiliate_clicks', 'commission_amount')) {
                $table->decimal('commission_amount', 10, 2)->default(0)->after('session_id');
            }
        });

    }

    public function down(): void
    {
        Schema::table('affiliate_clicks', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'created_at']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['referer', 'session_id', 'commission_amount']);
        });
    }
};