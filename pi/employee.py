class Employee:


    # CREATE EMPLOYEE OBJECT

    def __init__(
        self,
        employee_id,
        first_name,
        last_name,
        rfid_uid,
        status="OUT",
        last_scan=None
    ):


        self.employee_id = employee_id

        self.first_name = first_name

        self.last_name = last_name

        self.rfid_uid = rfid_uid

        self.status = status

        self.last_scan = last_scan



    # GET FULL NAME

    def get_full_name(self):

        return (
            f"{self.first_name} "
            f"{self.last_name}"
        )



    # CHECK IF EMPLOYEE IS CLOCKED IN

    def is_clocked_in(self):

        return self.status == "IN"



    # CLOCK IN

    def clock_in(self):

        self.status = "IN"



    # CLOCK OUT

    def clock_out(self):

        self.status = "OUT"



    # UPDATE LAST SCAN TIME

    def update_scan_time(self, scan_time):

        self.last_scan = scan_time



    # DISPLAY EMPLOYEE INFO

    def display_info(self):

        return {

            "ID": self.employee_id,

            "Name": self.get_full_name(),

            "RFID": self.rfid_uid,

            "Status": self.status,

            "Last Scan": self.last_scan

        }