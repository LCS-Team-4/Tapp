# reader.py — reads whatever string the RFID reader hands over. Nothing
# else: no parsing, no format checks, no card-registry lookups.
#
# Per docs/spec.md §5, the actual hardware in hand is a USB RFID reader
# that behaves as an HID keyboard device — "it types a card's UID as a
# string on tap, no drivers needed" — and that's true of every option still
# on the table there (tap-to-trigger vs. tap-to-write are both explicitly
# described as "the RFID reader picks that string up the same way it reads
# a card UID"). Reading it as a line of text is therefore not a guess about
# which of those wins; it's the one part of spec.md §5 that's already
# settled regardless.
#
# The old pi/rfid_reader.py assumed a *different* RFID module entirely — a
# SPI-wired mfrc522 board driven over RPi.GPIO — which isn't the hardware
# spec.md §5 lists as on hand, and needs a physical wiring/pin setup
# (RFID_RST_PIN etc.) this project was never going to use. That's a hardware
# mismatch fix, not an attempt to settle the open transport decision — see
# docs/firmware-reconciliation.md.
#
# This class's *interface* (read() returns a raw string or None) is what
# scanner.py depends on. Swap its body out once the transport decision
# lands if it turns out to need something other than a blocking line read;
# nothing else in firmware/ should need to change.


class Reader:
    def read(self):
        """Blocks until one scan is available (the reader itself sends the
        trailing newline on tap, same as pressing Enter after typing).
        Returns the raw string exactly as read, or None if nothing usable
        came through."""
        try:
            raw = input()
        except EOFError:
            return None
        raw = raw.strip()
        return raw or None
