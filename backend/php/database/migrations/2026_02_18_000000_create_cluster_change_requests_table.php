<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CIE v2.3.2 Patch 5 — Cluster governance: propose → review → approve workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('cluster_id', 50)->nullable()->index();
            $table->string('proposed_cluster_id', 80)->nullable();
            $table->string('proposed_name', 200)->nullable();
            $table->string('proposed_category', 100)->nullable();
            $table->string('intent_statement', 1000);
            $table->json('query_evidence')->nullable();
            $table->json('sku_assignment')->nullable();
            $table->text('impact_assessment')->nullable();
            $table->text('commercial_justification')->nullable();
            $table->string('status', 20)->default('proposed')->index();
            $table->string('requested_by', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by', 100)->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_change_requests');
    }
};
