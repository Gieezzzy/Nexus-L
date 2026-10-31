"""Confidence calibration: turns the raw signals of the best candidate into the probability that it is the RIGHT answer.

Features of the top candidate: hybrid score (meaning + word coverage), reranker score, word coverage, margin to the runner-up (both the
hybrid and the reranker one) and the question length. A small logistic regression (numpy only, L2 regularised, standardised inputs) is
fitted on labelled cases: label 1 = the top candidate was the expected answer, 0 = it was a different answer or the question should
not be answered at all. The fitted weights are stored in settings_versions.calib and used as the confidence.
"""
from __future__ import annotations

import math
from typing import Any

import numpy as np

FEATURES = ["hybrid", "rr", "cover", "margin", "rr_margin", "qlen"]


def vector(f: dict[str, float]) -> list[float]:
    return [float(f.get(k, 0.0)) for k in FEATURES]


def fit(rows: list[dict[str, float]], labels: list[int], l2: float = 0.05, epochs: int = 2500, lr: float = 0.3) -> dict[str, Any] | None:
    if len(rows) < 60 or len(set(labels)) < 2:
        return None
    X = np.array([vector(r) for r in rows], dtype=float)
    y = np.array(labels, dtype=float)
    mu, sd = X.mean(axis=0), X.std(axis=0)
    sd[sd < 1e-6] = 1.0
    Z = (X - mu) / sd
    w = np.zeros(Z.shape[1])
    b = 0.0
    for _ in range(epochs):                                      # full-batch gradient descent on the log loss
        p = 1.0 / (1.0 + np.exp(-(Z @ w + b)))
        g = Z.T @ (p - y) / len(y) + l2 * w
        w -= lr * g
        b -= lr * float((p - y).mean())
    p = 1.0 / (1.0 + np.exp(-(Z @ w + b)))
    eps = 1e-9
    loss = float(-(y * np.log(p + eps) + (1 - y) * np.log(1 - p + eps)).mean())
    return {"features": FEATURES, "mu": mu.tolist(), "sd": sd.tolist(), "w": w.tolist(), "b": b, "n": len(y), "positive": float(y.mean()), "log_loss": round(loss, 4)}


def predict(model: dict[str, Any] | None, f: dict[str, float]) -> float | None:
    if not model:
        return None
    x = np.array(vector(f))
    z = float(((x - np.array(model["mu"])) / np.array(model["sd"])) @ np.array(model["w"]) + model["b"])
    return 1.0 / (1.0 + math.exp(-max(-30.0, min(30.0, z))))
