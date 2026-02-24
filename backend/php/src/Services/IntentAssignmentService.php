<?php
namespace App\Services;
use App\Models\Sku;
use App\Models\Cluster;
use App\Models\SkuIntent;
use Illuminate\Support\Collection;
class IntentAssignmentService
{
 public function assignClusters(Sku $sku, array $clusterIds): array
 {
 $clusters = Cluster::whereIn('id', $clusterIds)->get();
 if ($clusters->count() !== count($clusterIds)) { throw new \InvalidArgumentException('One or more invalid cluster IDs'); }
 $sku->skuIntents()->delete();
 $assignedIntents = [];
 $isPrimary = true;
 foreach ($clusters as $cluster) {
 $skuIntent = SkuIntent::create([
 'sku_id' => $sku->id,
 'intent_id' => $cluster->primary_intent_id,
 'cluster_id' => $cluster->id,
 'is_primary' => $isPrimary
 ]);
 $assignedIntents[] = [
 'intent' => $cluster->primaryIntent->name,
 'intent_display' => $cluster->primaryIntent->display_name,
 'cluster' => $cluster->name,
 'cluster_id' => $cluster->id,
 'is_primary' => $isPrimary
 ];
 $isPrimary = false;
 }
 $sku->update(['primary_cluster_id' => $clusters->first()->id]);
 return $assignedIntents;
 }
 
 public function validateIntentAssignment(Sku $sku): bool
 {
 return $sku->skuIntents()->count() > 0;
 }
}
