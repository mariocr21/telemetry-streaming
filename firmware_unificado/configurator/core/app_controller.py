
from PySide6.QtCore import QObject, Signal

class AppController(QObject):
    """
    Central controller for independent business logic.
    Manages state and orchestrates communication between components.
    """
    
    # Signals to notify UI of state changes
    source_mode_changed = Signal(str)
    
    def __init__(self):
        super().__init__()
        self._source_mode = "CAN_ONLY"
        
    @property
    def source_mode(self):
        return self._source_mode
    
    @source_mode.setter
    def source_mode(self, mode):
        if self._source_mode != mode:
            self._source_mode = mode
            self.source_mode_changed.emit(mode)
