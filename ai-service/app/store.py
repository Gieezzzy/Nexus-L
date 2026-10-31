from __future__ import annotations

import os
import uuid
from typing import Any

from qdrant_client import QdrantClient
from qdrant_client.http import models as qm

from app.config import settings

# fastembed reads this: the model is downloaded once and then used offline
os.environ.setdefault("FASTEMBED_CACHE_PATH", str(settings.home / "models"))

_DIM = 384
_embedder = None
_client: QdrantClient | None = None
_QUERY_PREFIX = "Represent this sentence for searching relevant passages: "


def embedder():
    global _embedder
    if _embedder is None:
        from fastembed import TextEmbedding

        _embedder = TextEmbedding(settings.embed_model, cache_dir=str(settings.home / "models"))
    return _embedder


def embed(texts: list[str], *, query: bool = False) -> list[list[float]]:
    prefixed = [(_QUERY_PREFIX + t) if query else t for t in texts]
    return [v.tolist() for v in embedder().embed(prefixed)]


def client() -> QdrantClient:
    """Embedded Qdrant on disk (no server, no network). Only one process may hold it: the service itself."""
    global _client
    if _client is None:
        _client = QdrantClient(path=str(settings.home / "qdrant"))
        for name in ("docs", "corrections"):
            if not _client.collection_exists(name):
                _client.create_collection(name, vectors_config=qm.VectorParams(size=_DIM, distance=qm.Distance.COSINE))
    return _client


def point_id(kind: str, key: Any) -> str:
    return str(uuid.uuid5(uuid.NAMESPACE_URL, f"nexus-l:{kind}:{key}"))


def upsert(collection: str, items: list[dict[str, Any]]) -> None:
    """items: {key, text (what is embedded), payload}"""
    if not items:
        return
    vecs = embed([i["text"] for i in items])
    client().upsert(collection, points=[qm.PointStruct(id=point_id(collection, i["key"]), vector=v, payload=i["payload"]) for i, v in zip(items, vecs)])


def delete_missing(collection: str, keep_keys: set[Any]) -> int:
    keep = {point_id(collection, k) for k in keep_keys}
    old = []
    offset = None
    while True:
        pts, offset = client().scroll(collection, limit=500, offset=offset, with_payload=False)
        old += [str(p.id) for p in pts if str(p.id) not in keep]
        if offset is None:
            break
    if old:
        client().delete(collection, points_selector=qm.PointIdsList(points=old))
    return len(old)


def search(collection: str, text: str, k: int) -> list[dict[str, Any]]:
    v = embed([text], query=True)[0]
    res = client().query_points(collection, query=v, limit=k, with_payload=True).points
    return [{"score": float(p.score), "payload": dict(p.payload or {})} for p in res]


def count(collection: str) -> int:
    return client().count(collection).count
