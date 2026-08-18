import sys
import os

# Add the pi directory to the path so we can import the modules
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'pi'))

# Set database env vars for testing (same as backend/.env)
os.environ['DB_HOST'] = 'sql63.jnb2.host-h.net'
os.environ['DB_PORT'] = '3306'
os.environ['DB_NAME'] = 'digitcexse_db2'
os.environ['DB_USER'] = 'digitcexse_2'
os.environ['DB_PASSWORD'] = 'UfMEcUsfi1Mk8d9UEgg8'

from database import Database
from google_sheets_sync import GoogleSheetsSync

# Connect to the database
db = Database()
if not db.connect():
    print("FAIL: Could not connect to database")
    sys.exit(1)

# Test reading settings from the database
settings = db.get_settings()
print(f"Settings from DB: sync_enabled={settings.get('google_sheets_sync_enabled')}, webhook_url={settings.get('google_sheets_webhook_url')}")

# Test the sync using database settings
sync = GoogleSheetsSync(db)
result = sync.log_event('TEST-PI', 'Pi Test User', 'clock_in', 'device')
print(f"Result: {result}")

if result:
    print("PASS: Google Sheets sync from Pi works using database settings!")
else:
    print("FAIL: Google Sheets sync from Pi failed")

db.close()