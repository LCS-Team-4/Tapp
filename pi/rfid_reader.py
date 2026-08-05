from mfrc522 import SimpleMFRC522
import RPi.GPIO as GPIO
from config import RFID_RST_PIN



class RFIDReader:


    # INITIALIZE RFID READER

    def __init__(self):

        self.reader = SimpleMFRC522()

        print("RFID Reader Ready")



    # WAIT FOR CARD AND READ UID

    def read_card(self):

        try:

            print("Waiting for RFID card...")


            # Wait until card is detected
            uid, text = self.reader.read()


            print(
                "Card UID:",
                uid
            )


            return str(uid)



        except Exception as error:


            print(
                "RFID Error:",
                error
            )


            return None



    # CLEANUP RFID

    def cleanup(self):

        GPIO.cleanup()

        print(
            "RFID Reader Closed"
        )