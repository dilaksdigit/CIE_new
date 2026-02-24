"""
Cluster intent centroid vectors — cached in Redis (v2.3.1 §8.2.1).
Vectors are updated only when the SEO Governor updates a cluster's intent statement.
"""
import json
import os

import redis

r = redis.from_url(os.getenv('REDIS_URL', 'redis://localhost:6379/0'))
REDIS_KEY_PREFIX = "cluster:"


def get_cluster_vector(cluster_id):
    """Load cluster centroid vector from Redis. Returns None if not cached."""
    vec = r.get(f"{REDIS_KEY_PREFIX}{cluster_id}")
    if vec:
        return json.loads(vec)
    return None


def cache_cluster_vector(cluster_id, vector):
    """Store cluster centroid vector in Redis. Call when SEO Governor updates cluster intent."""
    r.set(f"{REDIS_KEY_PREFIX}{cluster_id}", json.dumps(vector))
