
from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QScrollArea, QFormLayout, QGroupBox, 
    QLineEdit, QCheckBox, QComboBox, QPushButton, QMessageBox
)
from PySide6.QtCore import Signal

class CloudTab(QWidget):
    check_wifi_requested = Signal()

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

        # Protocol Selection
        self.combo_protocol = QComboBox()
        self.combo_protocol.addItems(["http", "mqtt"])
        self.combo_protocol.setCurrentText("http")
        self.combo_protocol.currentTextChanged.connect(self.toggle_protocol_fields)
        layout.addRow("Protocol:", self.combo_protocol)

        # Upload Interval
        self.input_cloud_interval = QLineEdit("100")
        self.input_cloud_interval.setToolTip("Intervalo de subida en milisegundos (ms)")
        layout.addRow("Interval (ms):", self.input_cloud_interval)

        # MQTT Config Group
        self.mqtt_group = QGroupBox("MQTT Settings")
        mqtt_layout = QFormLayout()

        self.input_mqtt_server = QLineEdit("74.208.234.106")
        self.input_mqtt_port = QLineEdit("1883")
        self.input_mqtt_user = QLineEdit("telemetry")
        self.input_mqtt_pass = QLineEdit("Neurona123456T")
        self.input_mqtt_pass.setEchoMode(QLineEdit.Password)
        self.input_mqtt_topic = QLineEdit("vehicles/telemetry")

        self.chk_debug_mode = QCheckBox("Debug Mode (NO guardar en BD)")
        self.chk_debug_mode.setToolTip(
            "Si está marcado (d=true): Los datos NO se guardan en la base de datos.\n"
            "Si NO está marcado (d=false): Los datos SÍ se guardan en la base de datos."
        )
        self.chk_debug_mode.setChecked(False)

        mqtt_layout.addRow("Broker / Host:", self.input_mqtt_server)
        mqtt_layout.addRow("Port:", self.input_mqtt_port)
        mqtt_layout.addRow("MQTT User:", self.input_mqtt_user)
        mqtt_layout.addRow("MQTT Password:", self.input_mqtt_pass)
        mqtt_layout.addRow("Topic:", self.input_mqtt_topic)
        mqtt_layout.addRow("", self.chk_debug_mode)

        self.mqtt_group.setLayout(mqtt_layout)
        layout.addRow(self.mqtt_group)

        # Test Connection Button
        self.btn_check_wifi = QPushButton("Check WiFi Status")
        # Ensure we have consistent style for buttons if applied globally
        self.btn_check_wifi.setProperty("variant", "info")
        self.btn_check_wifi.clicked.connect(self.check_wifi_requested.emit)
        layout.addRow("", self.btn_check_wifi)

        # Initial State
        self.toggle_protocol_fields(self.combo_protocol.currentText())

    def toggle_protocol_fields(self, protocol):
        self.mqtt_group.setVisible(protocol == "mqtt")
