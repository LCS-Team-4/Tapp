import json
import urllib.request
import urllib.error
from datetime import datetime

# Google Sheets sync for the Pi terminal.
# Sends clock-in/clock-out events to the Google Apps Script Web App URL
# configured in the TAPP admin portal (settings table). The Apps Script
# appends a row to the Google Sheet for each event.
#
# The webhook URL and sync-enabled flag are read from the `settings` table
# in the database — the same source the PHP backend uses. This means the
# Pi automatically picks up the configuration from TAPP Admin → Settings
# without needing a separate .env file.
#
# All failures are logged but never break the clock flow.


class GoogleSheetsSync:

    def __init__(self, database, logger=None):
        self.database = database
        self.logger = logger

    def log_event(self, employee_id, employee_name, event_type, source="device"):
        """Send a clock event to the Google Sheet. Returns True on success."""
        settings = self._get_settings()
        if not settings:
            self._log("Google Sheets sync: could not read settings from database")
            return False

        sync_enabled = str(settings.get("google_sheets_sync_enabled", "0")).strip()
        if sync_enabled not in ("1", "true", "True", "TRUE", "yes"):
            return False

        webhook_url = (settings.get("google_sheets_webhook_url") or "").strip()
        if not webhook_url:
            self._log("Google Sheets sync enabled but no webhook URL configured")
            return False

        # Validate the URL looks like a Google Apps Script Web App.
        if not webhook_url.startswith("https://script.google.com/macros/s/") or not webhook_url.endswith("/exec"):
            self._log("Google Sheets sync: invalid webhook URL")
            return False

        payload = {
            "timestamp": datetime.now().astimezone().strftime("%Y-%m-%d %H:%M:%S"),
            "employee_id": employee_id,
            "employee_name": employee_name,
            "event_type": event_type,
            "source": source,
        }

        data = json.dumps(payload).encode("utf-8")

        req = urllib.request.Request(
            webhook_url,
            data=data,
            headers={
                "Content-Type": "text/plain",
                "User-Agent": "TAPP-Sync/1.0",
            },
            method="POST",
        )

        try:
            with urllib.request.urlopen(req, timeout=10) as resp:
                status = resp.getcode()
                body = resp.read().decode("utf-8", errors="replace")
                if status >= 400:
                    self._log(f"Google Sheets sync failed: HTTP {status} - {body[:200]}")
                    return False
                self._log(f"Google Sheets sync OK: {event_type} for {employee_id}")
                return True
        except urllib.error.HTTPError as e:
            self._log(f"Google Sheets sync HTTP error: {e.code} - {e.reason}")
            return False
        except urllib.error.URLError as e:
            self._log(f"Google Sheets sync connection error: {e.reason}")
            return False
        except Exception as e:
            self._log(f"Google Sheets sync error: {e}")
            return False

    def _get_settings(self):
        """Read the Google Sheets sync settings from the database settings table."""
        try:
            return self.database.get_settings()
        except Exception as e:
            self._log(f"Google Sheets sync: error reading settings: {e}")
            return None

    def _log(self, message):
        if self.logger:
            self.logger.info(message)
        else:
            print(message)