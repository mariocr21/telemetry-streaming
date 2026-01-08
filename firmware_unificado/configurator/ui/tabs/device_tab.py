
from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QScrollArea, QFormLayout, QGroupBox, 
    QLineEdit, QSpinBox, QCheckBox, QComboBox
)
from PySide6.QtCore import Signal
from core.constants import DEFAULT_CLOUD_INTERVAL_MS, DEFAULT_SERIAL_INTERVAL_MS

class DeviceTab(QWidget):
    source_changed = Signal(str)

    def __init__(self, parent=None):
        super().__init__(parent)
        self.setup_ui()

    def setup_ui(self):
        outer = QVBoxLayout(self)
        outer.setContentsMargins(0, 0, 0, 0)

        scroll = QScrollArea()
        scroll.setWidgetResizable(True)
        scroll.setStyleSheet("QScrollArea { border: none; }")
        outer.addWidget(scroll)

        content = QWidget()
        scroll.setWidget(content)

        layout = QFormLayout(content)
        layout.setContentsMargins(24, 24, 24, 24)
        layout.setSpacing(16)

        # WiFi Group
        wifi_group = QGroupBox("WiFi Configuration")
        wifi_layout = QFormLayout()
        self.input_ssid = QLineEdit()
        self.input_pass = QLineEdit()
        self.input_pass.setEchoMode(QLineEdit.Password)
        wifi_layout.addRow("SSID:", self.input_ssid)
        wifi_layout.addRow("Password:", self.input_pass)
        wifi_group.setLayout(wifi_layout)
        layout.addRow(wifi_group)

        # Device Group
        device_group = QGroupBox("Device Identity")
        device_layout = QFormLayout()
        self.input_device_id = QLineEdit("00000000000000001")
        self.input_car_id = QLineEdit("OBD-2025-0001")
        self.input_api_url = QLineEdit("https://telemetry.neurona.xyz/api/registers")

        self.combo_baud = QComboBox()
        self.combo_baud.addItems(["500", "1000", "250"])
        self.combo_baud.setCurrentText("500")

        self.combo_crystal = QComboBox()
        self.combo_crystal.addItems(["8", "16"])
        self.combo_crystal.setCurrentText("8")

        self.input_serial_interval = QLineEdit(str(DEFAULT_SERIAL_INTERVAL_MS))

        # ========================
        # MODO DE OPERACIÓN - SOLO 3 MODOS REALES
        # ========================
        self.combo_source = QComboBox()
        self.combo_source.setMinimumHeight(40)
        self.combo_source.setStyleSheet("""
            QComboBox {
                font-size: 14px;
                font-weight: bold;
                padding: 8px 12px;
            }
        """)
        
        # PURGED: OBD_DIRECT and CAN_OBD (not supported by hardware)
        self.source_map = {
            "🏎️ CAN (MCP2515)": "CAN_ONLY",
            "🔌 OBD Bridge (ESP32-C3)": "OBD_BRIDGE",
            "📍 Tracking (Solo GPS/IMU)": "SENSORS_ONLY"
        }
        self.combo_source.addItems(self.source_map.keys())
        self.combo_source.currentTextChanged.connect(self._on_source_changed_internal)
        
        device_layout.addRow("Modo de Operación:", self.combo_source)

        # IMU integrada
        self.chk_imu_enabled = QCheckBox("Habilitar IMU (MPU6050)")
        self.chk_imu_enabled.setChecked(False)

        device_layout.addRow("Device ID:", self.input_device_id)
        device_layout.addRow("Car ID:", self.input_car_id)
        device_layout.addRow("API URL:", self.input_api_url)
        device_layout.addRow("Serial Telemetry Interval (ms):", self.input_serial_interval)
        device_layout.addRow("Fuente de datos (source):", self.combo_source)
        device_layout.addRow("CAN Baud Rate (kbps):", self.combo_baud)
        device_layout.addRow("Crystal Freq (MHz):", self.combo_crystal)
        device_layout.addRow("IMU (MPU6050):", self.chk_imu_enabled)

        device_group.setLayout(device_layout)
        layout.addRow(device_group)

        # Pins
        pin_group = QGroupBox("Hardware Pins")
        pin_layout = QFormLayout()

        self.input_cs_pin = QSpinBox()
        self.input_cs_pin.setRange(0, 40)
        self.input_cs_pin.setValue(5)
        pin_layout.addRow("CAN CS Pin (GPIO):", self.input_cs_pin)

        self.input_int_pin = QSpinBox()
        self.input_int_pin.setRange(0, 40)
        self.input_int_pin.setValue(4)
        pin_layout.addRow("CAN INT Pin (GPIO):", self.input_int_pin)

        pin_group.setLayout(pin_layout)
        layout.addRow(pin_group)

        # GPS
        gps_group = QGroupBox("GPS Settings (Módulo Integrado)")
        gps_layout = QFormLayout()

        self.chk_gps_enabled = QCheckBox("Habilitar GPS integrado")
        self.chk_gps_enabled.setToolTip(
            "Habilita el módulo GPS integrado en nuestra tarjeta.\n"
            "Algunos Motec ya traen GPS, en ese caso déjalo deshabilitado."
        )
        self.chk_gps_enabled.setChecked(False)
        gps_layout.addRow("", self.chk_gps_enabled)

        self.input_gps_rx_pin = QSpinBox()
        self.input_gps_rx_pin.setRange(0, 40)
        self.input_gps_rx_pin.setValue(16)
        gps_layout.addRow("GPS RX Pin (GPIO):", self.input_gps_rx_pin)

        self.input_gps_tx_pin = QSpinBox()
        self.input_gps_tx_pin.setRange(0, 40)
        self.input_gps_tx_pin.setValue(17)
        gps_layout.addRow("GPS TX Pin (GPIO):", self.input_gps_tx_pin)

        gps_group.setLayout(gps_layout)
        layout.addRow(gps_group)

    def _on_source_changed_internal(self, text):
        val = self.source_map.get(text, "CAN_ONLY")
        self.source_changed.emit(val)

    def get_source_value(self):
        return self.source_map.get(self.combo_source.currentText(), "CAN_ONLY")

    def set_source_value_by_code(self, code):
        reverse_map = {v: k for k, v in self.source_map.items()}
        ui_text = reverse_map.get(code, "🏎️ CAN (MCP2515)")
        self.combo_source.setCurrentText(ui_text)
