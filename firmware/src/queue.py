# queue.py — short-lived retry queue for redeem attempts that failed with a
# transient network error (RedeemUnreachable — see api_client.py). Nothing
# in pi/ had an equivalent: it's new, not a straight port of anything.
#
# Deliberately bounded, not a durable offline store like the PWAs'
# IndexedDB write queue (docs/spec.md §11): a clock-in token is valid for
# only TOKEN_TTL_SECONDS (backend/app/Services/TokenService.php's TTL,
# mirrored in config.py). Once an entry is older than that, no amount of
# retrying can ever redeem it — so it's dropped, not held indefinitely.
#
# Note for future maintainers: this file is named queue.py to match
# docs/architecture.md's scaffolded layout, which shadows the stdlib
# `queue` module used internally by requests' urllib3 dependency. That's
# exactly why firmware/src/ is a package (__init__.py) using relative
# imports and is meant to be run as `python -m src.scanner` from firmware/
# — never `python scanner.py` from inside src/, which would put src/ itself
# on sys.path and break `import requests`. See scanner.py's header.
import time

from .config import TOKEN_TTL_SECONDS


class RetryQueue:
    def __init__(self):
        self._pending = []  # list of (token, first_seen_at)

    def enqueue(self, token):
        self._pending.append((token, time.time()))

    def flush(self, send):
        """
        Retries every queued token via send(token) -> bool, where True means
        "handled, drop it" (either it succeeded, or it failed in a way
        that's final, not transient) and False means "still transient, keep
        it for the next flush." Entries older than TOKEN_TTL_SECONDS are
        dropped unconditionally without calling send() — they cannot
        possibly redeem successfully anymore.
        """
        still_pending = []
        for token, first_seen_at in self._pending:
            if time.time() - first_seen_at > TOKEN_TTL_SECONDS:
                continue
            if not send(token):
                still_pending.append((token, first_seen_at))
        self._pending = still_pending

    def __len__(self):
        return len(self._pending)
