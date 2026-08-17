import os
from dotenv import load_dotenv
load_dotenv() 

# COMPANY SETTINGS
COMPANY_NAME = "TAPP"

# DATABASE SETTINGS
PI_DB_HOST=os.getenv("DB_HOST")
PI_DB_PORT=os.getenv("DB_PORT")
PI_DB_NAME=os.getenv("DB_NAME")
PI_DB_USER=os.getenv("DB_USER")
PI_DB_PASSWORD=os.getenv("DB_PASSWORD")

# RFID SETTINGS
# Reset Pin (GPIO)
RFID_RST_PIN = 25
# SPI Bus
SPI_BUS = 0
# SPI Device (CE0)
SPI_DEVICE = 0

# ATTENDANCE SETTINGS
# Prevent accidental double scans
COOLDOWN_MINUTES = 15
# Time format
TIME_FORMAT = "%H:%M:%S"
DATE_FORMAT = "%Y-%m-%d"
DATETIME_FORMAT = "%Y-%m-%d %H:%M:%S"

# GOOGLE SHEETS SYNC
# Set GOOGLE_SHEETS_SYNC_ENABLED=1 and GOOGLE_SHEETS_WEBHOOK_URL in the Pi's
# .env file to enable syncing clock events to Google Sheets. The webhook URL
# is the Google Apps Script Web App URL from the TAPP admin portal settings.
GOOGLE_SHEETS_SYNC_ENABLED = os.getenv("GOOGLE_SHEETS_SYNC_ENABLED", "0")
GOOGLE_SHEETS_WEBHOOK_URL = os.getenv("GOOGLE_SHEETS_WEBHOOK_URL", "")

# LOGGING
LOG_FILE = "logs/attendance.log"
LOG_LEVEL = "INFO"

