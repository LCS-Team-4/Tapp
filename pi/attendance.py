import json
import urllib.request
import urllib.error
from datetime import datetime, timedelta
from config import COOLDOWN_MINUTES


class AttendanceManager:


    def __init__(self, database):

        self.database = database



    # PROCESS RFID SCAN

    def process_scan(self, uid):

        print(
            "Processing card:",
            uid
        )


        # Find user

        user = self.database.get_employee_by_uid(uid)


        if not user:

            print(
                " Unknown RFID Card"
            )

            return False



        employee_id = user["employee_id"]

        name = (
            user["first_name"]
            + " "
            + user["last_name"]
        )


        print(
            "Employee:",
            name
        )



        # Check cooldown

        if self.is_on_cooldown(employee_id):

             print(
                " Please wait before scanning again"
            )

             return False



        # Check current status

        status = user["status"]



        current_time = datetime.now()



        # CLOCK IN

        if status == "OUT":


            self.database.save_attendance(

                employee_id,

                "CLOCK_IN",

                current_time

            )


            self.database.update_status(

                employee_id,

                "IN"

            )


            self.database.update_last_scan(

                employee_id,

                current_time

            )


            print(
                "✅ Clocked In:",
                name
            )


            self.sync_to_google_sheets(
                employee_id,
                name,
                "clock_in"
            )


            return True



        # CLOCK OUT

        elif status == "IN":


            self.database.save_attendance(

                employee_id,

                "CLOCK_OUT",

                current_time

            )


            self.database.update_status(

                employee_id,

                "OUT"

            )


            self.database.update_last_scan(

                employee_id,

                current_time

            )


            print(
                "✅ Clocked Out:",
                name
            )


            self.sync_to_google_sheets(
                employee_id,
                name,
                "clock_out"
            )


            return True


    # SEND EVENT TO GOOGLE SHEETS
    # Best-effort: failures are logged but never block the clock flow.

    def sync_to_google_sheets(
            self,
            employee_id,
            employee_name,
            event_type
        ):

        try:

            webhook_url = self.database.get_google_sheets_webhook()

            if not webhook_url:
                return

            payload = {
                "timestamp": datetime.now().isoformat(),
                "employee_id": employee_id,
                "employee_name": employee_name,
                "event_type": event_type,
                "source": "device"
            }

            data = json.dumps(payload).encode("utf-8")

            request = urllib.request.Request(
                webhook_url,
                data=data,
                headers={
                    "Content-Type": "text/plain",
                    "User-Agent": "TAPP-Sync/1.0"
                },
                method="POST"
            )

            with urllib.request.urlopen(request, timeout=10) as response:
                response.read()

            print(
                "📊 Synced to Google Sheets:",
                event_type
            )

        except urllib.error.URLError as error:
            print(
                "⚠️ Google Sheets sync failed:",
                error
            )

        except Exception as error:
            print(
                "⚠️ Google Sheets sync error:",
                error
            )



    # CHECK 15 MINUTE COOLDOWN

    def is_on_cooldown(self, employee_id):


        last_scan = self.database.get_last_scan(
            employee_id
        )


        # First scan ever
        if not last_scan:

            return False



        current_time = datetime.now()


        difference = (
            current_time - last_scan
        )



        if difference < timedelta(
            minutes=COOLDOWN_MINUTES
        ):

            return True



        return False