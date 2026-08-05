# Marks firmware/src/ as a package so its modules can use relative imports
# (from .config import ...) and are run as `python -m src.scanner` from
# firmware/ — see scanner.py's header for why that matters (queue.py's
# filename shadows the stdlib module requests' urllib3 dependency needs).
