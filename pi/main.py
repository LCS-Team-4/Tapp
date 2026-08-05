from database import Database
from rfid_reader import RFIDReader
from attendance import AttendanceManager
from logger import SystemLogger



def main():


    # Start Logger

    logger = SystemLogger()

    logger.info(
        "TAPP Attendance System Starting"
    )


    # Connect Database

    database = Database()


    if not database.connect():

        logger.error(
            "Database connection failed"
        )

        return



    logger.info(
        "Database connected"
    )


    # Start RFID Reader

    rfid = RFIDReader()


    logger.info(
        "RFID Reader Ready"
    )


    # Start Attendance Manager

    attendance = AttendanceManager(
        database
    )


    logger.info(
        "Attendance System Ready"
    )



    # Main Loop

    try:


        while True:


            # Wait for card

            uid = rfid.read_card()



            if uid:


                logger.info(
                    f"Card detected: {uid}"
                )


                result = attendance.process_scan(
                    uid
                )


                if result:

                    logger.info(
                        "Attendance recorded"
                    )


                else:

                    logger.warning(
                        "Attendance rejected"
                    )



    except KeyboardInterrupt:


        logger.info(
            "System stopped manually"
        )



    except Exception as error:


        logger.error(
            f"System error: {error}"
        )



    finally:


        database.close()

        rfid.cleanup()


        logger.info(
            "System shutdown complete"
        )



# Run Program

if __name__ == "__main__":

    main()