# scanner.py — entry point. Reads scans in a loop, enforces the cooldown,
# forwards each scan to the backend via api_client.redeem(), and logs the
# outcome. Folds together what used to be split across pi/main.py (the main
# loop), pi/attendance.py (cooldown + per-scan orchestration), and
# pi/logger.py (logging setup) — docs/architecture.md's scaffolded layout
# has no separate logger.py, so that setup lives here now. See
# docs/firmware-reconciliation.md for the full old-file -> new-file mapping.
#
# Run with:  python -m src.scanner   (from the firmware/ directory)
# NOT:       python scanner.py       (from inside firmware/src/)
# The second form puts firmware/src/ itself on sys.path, which shadows the
# stdlib `queue` module that requests' urllib3 dependency imports
# internally — `import requests` would fail. Running as a package module
# (the first form) avoids that; see queue.py's header for the full story.
import logging
import os
import threading
import time

from .api_client import RedeemRejected, RedeemUnreachable, redeem
from .config import COOLDOWN_MINUTES, LOG_FILE, LOG_LEVEL
from .queue import RetryQueue
from .reader import Reader

# How often the background thread retries anything still in the queue.
FLUSH_INTERVAL_SECONDS = 5


def setup_logging():
    folder = os.path.dirname(LOG_FILE)
    if folder and not os.path.exists(folder):
        os.makedirs(folder)

    logging.basicConfig(
        filename=LOG_FILE,
        level=getattr(logging, LOG_LEVEL, logging.INFO),
        format="%(asctime)s | %(levelname)s | %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
    )
    return logging.getLogger("TAPP")


class CooldownTracker:
    """Prevents an accidental double-scan of the same card/tag within
    COOLDOWN_MINUTES. The old pi/attendance.py tracked this per
    employee_id, via a database round-trip on every scan
    (get_last_scan/update_last_scan) — firmware no longer knows *who*
    scanned until the backend's response tells it, so this keys on the one
    thing it has up front: the raw scanned string itself, held in memory."""

    def __init__(self, cooldown_minutes):
        self._cooldown_seconds = cooldown_minutes * 60
        self._last_scan_at = {}

    def is_on_cooldown(self, raw):
        last = self._last_scan_at.get(raw)
        if last is None:
            return False
        return (time.time() - last) < self._cooldown_seconds

    def mark_scanned(self, raw):
        self._last_scan_at[raw] = time.time()


def attempt_redeem(logger, raw):
    """Sends one scanned string to the backend and logs the outcome.
    Returns True if this attempt is fully handled (redeemed, or rejected
    outright by the backend — nothing more to do either way), False if it
    should be retried (a transient network failure only)."""
    try:
        result = redeem(raw)
    except RedeemRejected as error:
        logger.warning(f"Redeem rejected: {error}")
        return True
    except RedeemUnreachable as error:
        logger.error(f"Backend unreachable, will retry: {error}")
        return False
    else:
        logger.info(
            f"{result.get('action')}: {result.get('employee_name')} at {result.get('time')}"
        )
        return True


def run_flush_loop(logger, retry_queue, stop_event):
    """Background thread: retries anything still queued every
    FLUSH_INTERVAL_SECONDS, independent of the main loop below (which
    blocks on reader.read() and could otherwise wait far longer than a
    queued token's ~30s TTL before getting another chance to retry it)."""
    while not stop_event.wait(FLUSH_INTERVAL_SECONDS):
        if len(retry_queue):
            retry_queue.flush(lambda raw: attempt_redeem(logger, raw))


def run():
    logger = setup_logging()
    logger.info("TAPP terminal starting")

    reader = Reader()
    cooldown = CooldownTracker(COOLDOWN_MINUTES)
    retry_queue = RetryQueue()

    stop_event = threading.Event()
    flush_thread = threading.Thread(
        target=run_flush_loop, args=(logger, retry_queue, stop_event), daemon=True
    )
    flush_thread.start()

    logger.info("Ready for scans")

    try:
        while True:
            raw = reader.read()
            if not raw:
                continue

            logger.info(f"Scan read: {raw}")

            if cooldown.is_on_cooldown(raw):
                logger.warning(f"Ignored (cooldown): {raw}")
                continue

            cooldown.mark_scanned(raw)

            if not attempt_redeem(logger, raw):
                retry_queue.enqueue(raw)
    except KeyboardInterrupt:
        logger.info("Terminal stopped manually")
    finally:
        stop_event.set()
        logger.info("Terminal shutdown complete")


if __name__ == "__main__":
    run()
