import sys
import os

# Add the pi directory to the path so we can import the modules
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'pi'))

# Set environment variables for testing
os.environ['GOOGLE_SHEETS_SYNC_ENABLED'] = '1'
os.environ['GOOGLE_SHEETS_WEBHOOK_URL'] = 'https://script.google.com/macros/s/AKfycbyq6dr35Edy_TfIpGeut0NYkgX-7x99dOImkshsAgvz88yJyvfLZBz45m2FDhWUNhywZw/exec'

from google_sheets_sync import GoogleSheetsSync

# Test the sync
sync = GoogleSheetsSync()
result = sync.log_event('TEST-PI', 'Pi Test User', 'clock_in', 'device')
print(f"Result: {result}")

if result:
    print("PASS: Google Sheets sync from Pi works!")
else:
    print("FAIL: Google Sheets sync from Pi failed")