import mysql.connector
from mysql.connector import Error

from config import (
    PI_DB_HOST,
    PI_DB_PORT,
    PI_DB_NAME,
    PI_DB_USER,
    PI_DB_PASSWORD
)


class Database:

    def __init__(self):

        self.connection = None
        self.cursor = None


    # CONNECT TO MYSQL

    def connect(self):

        try:

            self.connection = mysql.connector.connect(

                host=PI_DB_HOST,
                port=PI_DB_PORT,
                database=PI_DB_NAME,
                user=PI_DB_USER,
                password=PI_DB_PASSWORD

            )


            if self.connection.is_connected():

                self.cursor = self.connection.cursor(
                    dictionary=True
                )

                print("✅ MySQL Connected")

                return True


        except Error as error:

            print(
                "❌ Database connection failed:",
                error
            )

            return False



    # CLOSE DATABASE

    def close(self):

        if self.connection:

            self.cursor.close()

            self.connection.close()

            print("Database closed")



    # FIND EMPLOYEE USING RFID UID

    def get_employee_by_uid(self, uid):

        query = """

        SELECT *
        FROM users
        WHERE rfid_uid = %s

        """


        self.cursor.execute(
            query,
            (uid,)
        )


        user = self.cursor.fetchone()


        return user



    # GET CURRENT STATUS
    # IN = Working
    # OUT = Not working

    def get_user_status(self, employee_id):


        query = """

        SELECT status
        FROM users
        WHERE employee_id = %s

        """


        self.cursor.execute(
            query,
            (employee_id,)
        )


        result = self.cursor.fetchone()


        if result:

            return result["status"]


        return None



    # UPDATE STATUS
    # CLOCK IN / CLOCK OUT

    def update_status(
            self,
            employee_id,
            status
        ):


        query = """

        UPDATE users

        SET status = %s

        WHERE employee_id = %s

        """


        self.cursor.execute(
            query,
            (
                status,
                employee_id
            )
        )


        self.connection.commit()



    # GET LAST RFID SCAN TIME
    # USED FOR 15 MIN COOLDOWN

    def get_last_scan(self, employee_id):


        query = """

        SELECT last_scan

        FROM users

        WHERE employee_id = %s

        """


        self.cursor.execute(
            query,
            (employee_id,)
        )


        result = self.cursor.fetchone()


        if result:

            return result["last_scan"]


        return None



    # UPDATE LAST SCAN TIME

    def update_last_scan(
            self,
            employee_id,
            scan_time
        ):


        query = """

        UPDATE users

        SET last_scan = %s

        WHERE employee_id = %s

        """


        self.cursor.execute(
            query,
            (
                scan_time,
                employee_id
            )
        )


        self.connection.commit()



    # SAVE ATTENDANCE RECORD
    # action must be 'in' or 'out' — matches the attendance.action enum
    # (the old 'CLOCK_IN'/'CLOCK_OUT' values never matched the enum and
    # would fail on insert).

    def save_attendance(
            self,
            employee_id,
            action,
            timestamp
        ):

        # Normalize legacy/caller values to the schema enum
        if action in ("CLOCK_IN", "clock_in", "CHECK_IN"):
            action = "in"
        elif action in ("CLOCK_OUT", "clock_out", "CHECK_OUT"):
            action = "out"

        if action not in ("in", "out"):
            raise ValueError(f"Invalid attendance action: {action}")


        query = """

        INSERT INTO attendance

        (
            employee_id,
            action,
            attendance_time
        )

        VALUES

        (
            %s,
            %s,
            %s
        )

        """


        self.cursor.execute(

            query,

            (
                employee_id,
                action,
                timestamp
            )

        )


        self.connection.commit()



    # CHECK IF CARD EXISTS

    def card_registered(self, uid):


        query = """

        SELECT employee_id

        FROM users

        WHERE rfid_uid = %s

        """


        self.cursor.execute(
            query,
            (uid,)
        )


        result = self.cursor.fetchone()


        return result is not None