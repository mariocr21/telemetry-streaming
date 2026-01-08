import serial
import serial.tools.list_ports
import json
import time

class SerialManager:
    def __init__(self):
        self.serial_port = None

    def list_ports(self):
        """Returns a list of available COM ports."""
        ports = serial.tools.list_ports.comports()
        return [port.device for port in ports]

    def connect(self, port, baudrate=115200):
        """
        Connects to the specified serial port.
        CRITICAL: Prevents ESP32 reset by configuring DTR/RTS before opening.
        """
        try:
            # IMPORTANTE: Configurar DTR/RTS a False ANTES de abrir el puerto
            # Esto previene que el driver envíe señal de reset al ESP32
            self.serial_port = serial.Serial()
            self.serial_port.port = port
            self.serial_port.baudrate = baudrate
            self.serial_port.timeout = 1
            
            # Deshabilitar DTR/RTS ANTES de abrir (crítico para prevenir reset)
            self.serial_port.dtr = False
            self.serial_port.rts = False
            self.serial_port.dsrdtr = False  # Deshabilitar control de flujo DSR/DTR
            self.serial_port.rtscts = False  # Deshabilitar control de flujo RTS/CTS
            
            # Ahora abrir el puerto
            self.serial_port.open()
            
            # Pequeño delay para estabilizar la conexión
            import time
            time.sleep(0.3)
            
            # Limpiar buffers después de conectar
            self.serial_port.reset_input_buffer()
            self.serial_port.reset_output_buffer()
            
            return True, f"Connected to {port} (No Reset)"
        except serial.SerialException as e:
            self.serial_port = None
            return False, f"Serial Error: {str(e)}"
        except Exception as e:
            self.serial_port = None
            return False, f"Error: {str(e)}"


    @property
    def is_connected(self):
        return self.serial_port is not None and self.serial_port.is_open

    def disconnect(self):
        """Disconnects the serial port."""
        if self.serial_port and self.serial_port.is_open:
            self.serial_port.close()
            self.serial_port = None
            return True, "Disconnected"
        return False, "Not connected"

    def write(self, text):
        """Writes text to the serial port."""
        if self.serial_port and self.serial_port.is_open:
            try:
                self.serial_port.write(text.encode('utf-8'))
                self.serial_port.write(b"\n")
            except Exception:
                pass

    def send_config(self, config_data):
        """Sends the JSON configuration to the device."""
        if not self.serial_port or not self.serial_port.is_open:
            return False, "Not connected to device."

        try:
            # 1. Send Main Config (SET_CONFIG)
            # Serialize to JSON string (compact)
            # Note: The ESP32 ConfigManager.jsonToConfig() ignores "sensors", so we can send the whole blob
            # or strip it. Sending the whole blob is easier.
            json_str = json.dumps(config_data, separators=(',', ':'))
            cmd_config = f"SET_CONFIG:{json_str}\n"
            
            # Clear buffers
            self.serial_port.reset_input_buffer()
            self.serial_port.reset_output_buffer()
            
            # Send SET_CONFIG
            self.serial_port.write(cmd_config.encode('utf-8'))
            self.serial_port.flush()
            time.sleep(0.3) # Wait for processing
            
            # 2. Send Sensors (SET_SENSORS)
            if "sensors" in config_data:
                sensors_payload = {"sensors": config_data["sensors"]}
                sensors_json_str = json.dumps(sensors_payload, separators=(',', ':'))
                cmd_sensors = f"SET_SENSORS:{sensors_json_str}\n"
                
                self.serial_port.write(cmd_sensors.encode('utf-8'))
                self.serial_port.flush()
                time.sleep(0.3) # Wait for processing

            # 3. Persist to Flash (SAVE_CONFIG)
            self.serial_port.write(b"SAVE_CONFIG\n")
            self.serial_port.flush()
            
            return True, f"Configuration & Sensors sent successfully"
        except Exception as e:
            return False, f"Error sending data: {str(e)}"

    def read_line(self):
        """Reads a line from the serial port."""
        if self.serial_port and self.serial_port.is_open:
            try:
                # Check in_waiting only if open
                if self.serial_port.in_waiting > 0:
                    line = self.serial_port.readline().decode('utf-8').strip()
                    return line
            except Exception:
                # Return None on error (e.g. device disconnected improperly)
                return None
        return None

    def read_all_lines(self):
        """
        Reads ALL available lines from the serial buffer.
        This is critical for real-time systems to prevent buffer backlog and latency.
        Returns a list of lines (strings).
        """
        lines = []
        if self.serial_port and self.serial_port.is_open:
            try:
                # Drain the entire buffer in one go
                while self.serial_port.in_waiting > 0:
                    line = self.serial_port.readline().decode('utf-8').strip()
                    if line:
                        lines.append(line)
            except Exception:
                pass
        return lines

    def get_memory_stats(self):
        """
        Solicita estadísticas de memoria al ESP32 usando GET_STATUS.
        Retorna un diccionario aplanado para la UI.
        """
        if not self.is_connected:
            return None
        
        import time
        
        try:
            # Limpiar buffer
            self.serial_port.reset_input_buffer()
            
            # Enviar comando
            self.serial_port.write(b"GET_STATUS\n")
            self.serial_port.flush()
            
            start_time = time.time()
            timeout = 2.0
            
            while time.time() - start_time < timeout:
                line = self.read_line()
                if not line:
                    time.sleep(0.01)
                    continue
                
                if line.startswith("STATUS:"):
                    try:
                        json_str = line[7:] # Skip "STATUS:"
                        data = json.loads(json_str)
                        
                        # Map to flat structure expected by UI
                        stats = {}
                        mem = data.get("memory", {})
                        
                        stats["free_heap"] = mem.get("heap_free", 0)
                        stats["heap_size"] = mem.get("heap_total", 0)
                        stats["min_free_heap"] = mem.get("heap_min", 0)
                        stats["max_alloc"] = 0 # Not available in simple status
                        stats["fragmentation"] = 0 # Not available
                        
                        stats["sensor_count"] = data.get("sensors_count", 0)
                        stats["uptime_ms"] = data.get("uptime_ms", 0)
                        
                        # Determine status
                        free = stats["free_heap"]
                        if free < 50000:
                            stats["status"] = "CRITICAL"
                        elif free < 100000:
                            stats["status"] = "WARNING"
                        else:
                            stats["status"] = "OK"
                            
                        return stats
                    except Exception as e:
                        print(f"Error parsing STATUS JSON: {e}")
                        return None
                        
            return None
            
        except Exception as e:
            print(f"Error getting memory stats: {e}")
            return None
