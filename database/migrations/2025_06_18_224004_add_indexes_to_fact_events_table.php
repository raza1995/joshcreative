<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToFactEventsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fact_events', function (Blueprint $table) {
            $table->index(['event_type', 'event_ts'], 'idx_facts_type_ts');
            $table->index(['ref_domain_id', 'event_type', 'event_ts'], 'idx_facts_ref_type');
            $table->index(['product_id', 'event_type', 'event_ts'], 'idx_facts_prod_type');
            $table->index(['utm_source', 'utm_campaign'], 'idx_facts_utm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fact_events', function (Blueprint $table) {
            $table->dropIndex('idx_facts_type_ts');
            $table->dropIndex('idx_facts_ref_type');
            $table->dropIndex('idx_facts_prod_type');
            $table->dropIndex('idx_facts_utm');
        });
    }
}
