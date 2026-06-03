# SOURCE: Production Roadmap P4 — isolate gate tests from PostgreSQL audit_log writes
import pytest

from api.gates_validate import BusinessRules


@pytest.fixture(autouse=True)
def _no_audit_db(monkeypatch):
    """Prevent gate tests from blocking on audit_log DB connections."""

    def _noop(*args, **kwargs):
        return True

    monkeypatch.setattr("api.gates_validate.log_audit_event", _noop)


@pytest.fixture(autouse=True)
def rules_cache(monkeypatch):
    """In-memory BusinessRules — matches seeded gate keys used by run_g3/run_all_gates."""
    _vec_thr = 7 / 10 + 2 / 100
    cache = {
        "gates.answer_block_min_chars": 250,
        "gates.answer_block_max_chars": 300,
        "gates.best_for_min_entries": 2,
        "gates.not_for_min_entries": 1,
        "gates.vector_similarity_min": _vec_thr,
        "gates.description_word_count_min": 50,
        "gates.hero_max_secondary": 3,
        "gates.support_max_secondary": 2,
        "gates.harvest_max_secondary": 1,
    }
    monkeypatch.setattr(BusinessRules, "_cache", cache)
