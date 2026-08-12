from evdev import InputDevice, categorize, ecodes, list_devices
import time


class RFIDReader:

    # INITIALIZE RFID READER

    def __init__(self):

        self.device = None

        # Find the USB RFID Reader automatically
        for path in list_devices():

            device = InputDevice(path)

            if device.name == "IC Reader IC Reader":

                self.device = device
                break

        if self.device is None:

            raise Exception("RFID Reader Not Found")

        print("RFID Reader Ready")
        print(f"Using Device: {self.device.path}")



    # WAIT FOR CARD AND READ UID
    # Debounce: some USB readers emit the same UID twice per physical tap
    # (key-down repeat), which produced exact-duplicate attendance rows in
    # the live data. If the same UID is read again within DEBOUNCE_SECONDS,
    # it is ignored and the loop keeps waiting for a fresh card.

    def read_card(self):

        print("Waiting for RFID card...")

        keys = {

            ecodes.KEY_0: "0",
            ecodes.KEY_1: "1",
            ecodes.KEY_2: "2",
            ecodes.KEY_3: "3",
            ecodes.KEY_4: "4",
            ecodes.KEY_5: "5",
            ecodes.KEY_6: "6",
            ecodes.KEY_7: "7",
            ecodes.KEY_8: "8",
            ecodes.KEY_9: "9"

        }

        DEBOUNCE_SECONDS = 2.0
        last_uid = None
        last_read_time = 0.0

        uid = ""

        try:

            for event in self.device.read_loop():

                if event.type == ecodes.EV_KEY:

                    key = categorize(event)

                    if key.keystate == key.key_down:

                        if key.scancode == ecodes.KEY_ENTER:

                            now = time.time()

                            if uid == last_uid and (now - last_read_time) < DEBOUNCE_SECONDS:
                                print("Duplicate card read, ignoring:", uid)
                                uid = ""
                                continue

                            print("Card UID:", uid)

                            last_uid = uid
                            last_read_time = now
                            uid = ""

                            return last_uid

                        elif key.scancode in keys:

                            uid += keys[key.scancode]

        except Exception as error:

            print("RFID Error:", error)

            return None



    # CLEANUP RFID

    def cleanup(self):

        print("RFID Reader Closed")