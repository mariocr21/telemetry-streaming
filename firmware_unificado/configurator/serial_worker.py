from PySide6.QtCore import QThread, Signal
import time
import json

class SerialWorker(QThread):
    """
    Worker thread to handle blocking serial operations (download/upload)
    without freezing the main UI.
    """
    progress = Signal(str)      # Update status label
    finished = Signal(dict)     # Return result (config dict)
    error = Signal(str)         # Return error message

    def __init__(self, serial_manager, command, expected_prefix="CONFIG:", max_retries=3):
        super().__init__()
        self.serial_manager = serial_manager
        self.command = command
        self.expected_prefix = expected_prefix
        self.max_retries = max_retries

    def run(self):
        try:
            # Clear pending data
            self.serial_manager.read_all_lines()
            time.sleep(0.1)
            
            self.progress.emit(f"Requesting {self.command}...")
            self.serial_manager.write(self.command)
            
            json_str = ""
            started = False
            
            for attempt in range(self.max_retries):
                start_time = time.time()
                # Increase timeout for large JSONs
                timeout = 6.0 if attempt == 0 else 4.0
                
                while time.time() - start_time < timeout:
                    line = self.serial_manager.read_line()
                    
                    if not line:
                        time.sleep(0.01)
                        continue
                    
                    # Log debug lines that aren't config
                    if line.startswith('{"s":') or line.startswith('[DEVICE]'):
                        continue
                     
                    # Match Firmware format: PREFIX:{...json...}
                    if line.startswith(self.expected_prefix):
                        try:
                            json_part = line[len(self.expected_prefix):] # Skip prefix
                            data = json.loads(json_part)
                            self.finished.emit(data)
                            return
                        except json.JSONDecodeError as e:
                            self.error.emit(f"Invalid JSON in CONFIG response: {str(e)[:50]}")
                            return
                
                # If we get here, it's a timeout for this attempt
                
                # Retry logic
                if attempt < self.max_retries - 1:
                    self.progress.emit(f"Retry {attempt + 2}/{self.max_retries}...")
                    self.serial_manager.read_all_lines()
                    self.serial_manager.write(self.command)
                    started = False
                    json_str = ""
            
            self.error.emit("Timeout: No response from device")
            
        except Exception as e:
            self.error.emit(f"Worker Error: {str(e)}")
