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


        # Find employee

        employee = self.database.get_employee_by_uid(uid)


        if not employee:

            print(
                " Unknown RFID Card"
            )

            return False



        employee_id = employee["employee_id"]

        name = (
            employee["first_name"]
            + " "
            + employee["last_name"]
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

        status = employee["status"]



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