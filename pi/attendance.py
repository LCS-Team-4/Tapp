from datetime import datetime, timedelta
from config import COOLDOWN_MINUTES
from google_sheets_sync import GoogleSheetsSync


class AttendanceManager:


    def __init__(self, database, logger=None):

        self.database = database
        self.google_sheets = GoogleSheetsSync(database, logger=logger)



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


            # Sync to Google Sheets (best-effort, never blocks the clock flow)
            self.google_sheets.log_event(
                employee_id,
                name,
                "clock_in",
                "device"
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


            # Sync to Google Sheets (best-effort, never blocks the clock flow)
            self.google_sheets.log_event(
                employee_id,
                name,
                "clock_out",
                "device"
            )


            return True


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