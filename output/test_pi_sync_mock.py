import sys
import os

# Add the pi directory to the path so we can import the modules
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'pi'))

# Mock the database module since mysql.connector isn't available on this dev machine
class MockDatabase:
    def get_settings(self):
        return {
            'google_sheets_sync_enabled': 1,
            'google_sheets_webhook_url': 'https://script.google.com/macros/s/AKfycbyq6dr35Edy_TfIpGeut0NYkgX-7x99dOImkshsAgvz88yJyvfLZBz45m2FDhWUNhywZw/exec'
        }

from google_sheets_sync import GoogleSheetsSync

# Test with mock database
db = MockDatabase()
sync = GoogleSheetsSync(db)

# Test clock_in
result = sync.log_event('TEST-PI', 'Pi Test User', 'clock_in', 'device')
print(f"clock_in result: {result}")

# Test clock_out
result2 = sync.log_event('TEST-PI', 'Pi Test User', 'clock_out', 'device')
print(f"clock_out result: {result2}")

if result and result2:
    print("PASS: Google Sheets sync from Pi works using database settings!")
else:
    print("FAIL: Google Sheets sync from Pi failed")