
class Col:
    """Column indices for the CAN Mapping table - Single Source of Truth"""
    ENABLE = 0
    CAN_ID = 1
    CHANNEL = 2
    CLOUD_ID = 3
    OFFSET = 4
    LENGTH = 5
    MASK = 6
    TYPE = 7
    MULTIPLIER = 8
    DIVISOR = 9
    ADDER = 10
    MIN = 11
    MAX = 12
    UNIT = 13
    BYTE_ORDER = 14
    LIVE_VALUE = 15

class GenericMessage:
    def __init__(self, frame_id, name="ManualMsg"):
        self.frame_id = frame_id
        self.name = name
        self.signals = []

class GenericSignal:
    def __init__(self, name, start, length, is_signed, scale, offset, minimum, maximum, unit, byte_order="little_endian"):
        self.name = name
        self.start = start
        self.length = length
        self.is_signed = is_signed
        self.scale = scale
        self.offset = offset
        self.minimum = minimum
        self.maximum = maximum
        self.unit = unit
        self.byte_order = byte_order
        self.initial = 0
