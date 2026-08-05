import logging
import os

from config import (
    LOG_FILE,
    LOG_LEVEL
)



class SystemLogger:


    def __init__(self):

        self.setup_logger()



    # CREATE LOGGER

    def setup_logger(self):


        # Create logs folder if it doesn't exist

        folder = os.path.dirname(LOG_FILE)


        if not os.path.exists(folder):

            os.makedirs(folder)



        logging.basicConfig(

            filename=LOG_FILE,

            level=self.get_level(),

            format=(
                "%(asctime)s | "
                "%(levelname)s | "
                "%(message)s"
            ),

            datefmt="%Y-%m-%d %H:%M:%S"

        )


        self.logger = logging.getLogger(
            "TAPP"
        )



    # CONVERT TEXT LEVEL TO LOG LEVEL

    def get_level(self):


        if LOG_LEVEL == "DEBUG":

            return logging.DEBUG


        elif LOG_LEVEL == "ERROR":

            return logging.ERROR


        else:

            return logging.INFO



    # INFORMATION MESSAGE

    def info(self, message):

        self.logger.info(message)



    # ERROR MESSAGE

    def error(self, message):

        self.logger.error(message)



    # WARNING MESSAGE

    def warning(self, message):

        self.logger.warning(message)



    # DEBUG MESSAGE

    def debug(self, message):

        self.logger.debug(message)