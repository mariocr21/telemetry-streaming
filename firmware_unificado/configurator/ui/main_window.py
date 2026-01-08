"""
============================================================================
NEURONA OFF ROAD TELEMETRY - MAIN WINDOW (Production v1.0)
============================================================================

Clean architecture with exactly 3 supported hardware modes:
1. CAN_ONLY   - MCP2515 bus
2. OBD_BRIDGE - ESP32-C3 UART bridge to ELM327
3. SENSORS_ONLY - GPS/IMU tracking only

PURGED: OBD_DIRECT and CAN_OBD (hardware not supported)

Author: Neurona Racing Development
Date: 2024-12-23
Version: 1.0 Production
============================================================================
"""

import sys
import os
import json
import time
from datetime import datetime
from typing import Dict, Any, Optional

from PySide6.QtWidgets import (
    QMainWindow, QWidget, QVBoxLayout, QHBoxLayout, QLabel, 
    QPushButton, QSplitter, QTabWidget, QComboBox, QMessageBox, 
    QApplication, QDialog, QTextEdit, QFrame, QFileDialog
)
from PySide6.QtCore import Qt, QTimer, Slot
from PySide6.QtGui import QPixmap, QPalette, QColor, QFont

# ============================================================================
# LOCAL IMPORTS
# ============================================================================
from core.models import Col, GenericMessage, GenericSignal
from core.constants import (
    UI_REFRESH_INTERVAL_MS, 
    DEFAULT_CLOUD_INTERVAL_MS,
    DEFAULT_SERIAL_INTERVAL_MS
)
from core.app_controller import AppController

from ui.tabs.can_tab import CanTab
from ui.tabs.device_tab import DeviceTab
from ui.tabs.cloud_tab import CloudTab
from ui.tabs.obd_tab import ObdTab
from ui.tabs.live_tab import LiveTab
from ui.widgets.console import ConsoleWidget

from serial_manager import SerialManager
from json_generator import JSONGenerator


# ============================================================================
# CONSTANTS - ONLY 3 REAL HARDWARE MODES
# ============================================================================

# Mode-specific allowed JSON sections (PURGED: OBD_DIRECT, CAN_OBD)
MODE_ALLOWED_SECTIONS = {
    "CAN_ONLY": {
        "device", "wifi", "cloud", "serial", "can", "gps", "imu", "sensors"
    },
    "OBD_BRIDGE": {
        "device", "wifi", "cloud", "serial", "obd", "bridge_wifi", "gps", "imu", "fuel"
    },
    "SENSORS_ONLY": {
        "device", "wifi", "cloud", "serial", "gps", "imu"
    },
}

# Tab indices
TAB_SENSORS = 0
TAB_DEVICE = 1
TAB_CLOUD = 2
TAB_OBD = 3
TAB_LIVE = 4


class MainWindow(QMainWindow):
    """
    Main application window with 3-mode reactive UI.
    
    Hardware-aligned architecture:
    - CAN_ONLY: MCP2515 sensor capture
    - OBD_BRIDGE: ESP32-C3 UART bridge to ELM327
    - SENSORS_ONLY: GPS/IMU tracking only
    """
    
    def __init__(self, controller: AppController):
        super().__init__()
        self.controller = controller
        self.current_mode = "CAN_ONLY"
        
        self.setWindowTitle("Neurona Telemetry Configurator v1.0")
        self.resize(1280, 900)
        
        # Serial Manager
        self.serial_manager = SerialManager()
        self.simulation_active = False
        self.monitoring = False
        
        # Timer for data updates
        self.timer = QTimer()
        self.timer.timeout.connect(self.update_data)
        self.timer.start(UI_REFRESH_INTERVAL_MS)

        # Build UI
        self.setup_ui()
        self.load_stylesheet()
        
        # Connect controller signal
        self.controller.source_mode_changed.connect(self.on_controller_mode_changed)
        
        # Initial setup
        self.refresh_ports()
        self.update_ui_for_mode("CAN_ONLY")

    # ========================================================================
    # UI SETUP
    # ========================================================================
    
    def setup_ui(self):
        """Build the complete UI structure."""
        central_widget = QWidget()
        self.setCentralWidget(central_widget)
        main_layout = QVBoxLayout(central_widget)
        main_layout.setContentsMargins(12, 12, 12, 12)
        main_layout.setSpacing(12)

        # 1. Status Banner
        self.setup_status_banner(main_layout)
        
        # 2. Header
        self.setup_header(main_layout)

        # 3. Main Content
        self.splitter = QSplitter(Qt.Vertical)
        main_layout.addWidget(self.splitter, 1)

        self.tab_widget = QTabWidget()
        self.splitter.addWidget(self.tab_widget)

        self._create_tabs()

        # Console Widget
        self.console_widget = ConsoleWidget()
        self.splitter.addWidget(self.console_widget)
        self.console_widget.setVisible(False)
        self.splitter.setStretchFactor(0, 4)
        self.splitter.setStretchFactor(1, 1)

        # 4. Action Toolbar
        self.setup_actions(main_layout)

    def _create_tabs(self):
        """Instantiate and add all tabs."""
        # Tab 0: CAN Sensors
        self.tab_can = CanTab()
        self.tab_widget.addTab(self.tab_can, "📊 Sensores CAN")
        
        # Tab 1: Device Settings
        self.tab_device = DeviceTab()
        self.tab_widget.addTab(self.tab_device, "⚙️ Dispositivo")
        self.tab_device.source_changed.connect(self.on_source_changed)
        
        # Tab 2: Cloud Settings
        self.tab_cloud = CloudTab()
        self.tab_widget.addTab(self.tab_cloud, "☁️ Nube")
        self.tab_cloud.check_wifi_requested.connect(self.check_wifi_status)
        
        # Tab 3: OBD Bridge Settings
        self.tab_obd = ObdTab()
        self.tab_widget.addTab(self.tab_obd, "🔌 OBD Bridge")
        self.tab_obd.preview_requested.connect(self.show_obd_payload_preview)
        
        # Tab 4: Live Data
        self.tab_live = LiveTab()
        self.tab_widget.addTab(self.tab_live, "📡 En Vivo")

    def setup_status_banner(self, parent_layout):
        """Create the top status banner."""
        banner_frame = QFrame()
        banner_frame.setObjectName("statusBanner")
        banner_frame.setStyleSheet("""
            #statusBanner {
                background: qlineargradient(x1:0, y1:0, x2:1, y2:0,
                    stop:0 #2a2a2a, stop:0.5 #1e1e1e, stop:1 #2a2a2a);
                border: 1px solid #404040;
                border-radius: 8px;
                padding: 8px;
            }
        """)
        banner_layout = QHBoxLayout(banner_frame)
        banner_layout.setContentsMargins(16, 8, 16, 8)
        banner_layout.setSpacing(20)

        # Mode Badge
        self.lbl_mode_badge = QLabel("🏎️ MODO: CAN")
        self.lbl_mode_badge.setStyleSheet("""
            background-color: #ff6b35;
            color: white;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 14px;
        """)
        banner_layout.addWidget(self.lbl_mode_badge)
        
        # Connection Status
        self.lbl_connection_status = QLabel("⚪ DESCONECTADO")
        self.lbl_connection_status.setStyleSheet("""
            background-color: #404040;
            color: #b0b0b0;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 14px;
        """)
        banner_layout.addWidget(self.lbl_connection_status)
        
        # OBD Bridge Status (only visible in OBD_BRIDGE mode)
        self.lbl_obd_status = QLabel("C3: N/A")
        self.lbl_obd_status.setStyleSheet("""
            background-color: #404040;
            color: #b0b0b0;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 14px;
        """)
        self.lbl_obd_status.setVisible(False)
        banner_layout.addWidget(self.lbl_obd_status)

        banner_layout.addStretch()

        self.lbl_port_info = QLabel("Puerto: --")
        self.lbl_port_info.setStyleSheet("color: #888; font-size: 12px;")
        banner_layout.addWidget(self.lbl_port_info)

        parent_layout.addWidget(banner_frame)

    def setup_header(self, parent_layout):
        """Create the header with connection controls."""
        header_layout = QHBoxLayout()
        header_layout.setSpacing(12)
        
        # Logo
        logo_label = QLabel()
        logo_path = os.path.join(
            os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 
            "neurona-logo.png"
        )
        if os.path.exists(logo_path):
            pixmap = QPixmap(logo_path)
            pixmap = pixmap.scaledToHeight(50, Qt.SmoothTransformation)
            logo_label.setPixmap(pixmap)
        else:
            logo_label.setText("NEURONA")
            logo_label.setStyleSheet("font-size: 24px; font-weight: bold; color: #ff6b35;")
        header_layout.addWidget(logo_label)

        header_layout.addStretch()

        # Port Selection
        port_label = QLabel("Puerto:")
        port_label.setStyleSheet("color: #b0b0b0;")
        header_layout.addWidget(port_label)
        
        self.combo_ports = QComboBox()
        self.combo_ports.setMinimumWidth(150)
        self.combo_ports.setFixedHeight(36)
        header_layout.addWidget(self.combo_ports)

        self.btn_refresh = QPushButton("↻")
        self.btn_refresh.setFixedSize(36, 36)
        self.btn_refresh.setProperty("variant", "ghost")
        self.btn_refresh.setToolTip("Refrescar puertos")
        self.btn_refresh.clicked.connect(self.refresh_ports)
        header_layout.addWidget(self.btn_refresh)

        self.btn_connect = QPushButton("🔌 Conectar")
        self.btn_connect.setFixedHeight(36)
        self.btn_connect.setMinimumWidth(120)
        self.btn_connect.setProperty("variant", "success")
        self.btn_connect.clicked.connect(self.toggle_connection)
        header_layout.addWidget(self.btn_connect)

        header_layout.addSpacing(16)

        self.btn_toggle_console = QPushButton("📋 Consola")
        self.btn_toggle_console.setFixedHeight(36)
        self.btn_toggle_console.setProperty("variant", "ghost")
        self.btn_toggle_console.clicked.connect(self.toggle_console)
        header_layout.addWidget(self.btn_toggle_console)
        
        self.btn_monitor = QPushButton("▶ Monitor")
        self.btn_monitor.setFixedHeight(36)
        self.btn_monitor.setMinimumWidth(100)
        self.btn_monitor.setProperty("variant", "primary")
        self.btn_monitor.clicked.connect(self.toggle_monitoring)
        self.btn_monitor.setEnabled(False)
        header_layout.addWidget(self.btn_monitor)

        parent_layout.addLayout(header_layout)

    def setup_actions(self, parent_layout):
        """Create the bottom action toolbar."""
        action_layout = QHBoxLayout()
        action_layout.setSpacing(8)
        
        # CAN-specific buttons
        self.btn_load = QPushButton("📂 Cargar DBC")
        self.btn_load.setFixedHeight(42)
        self.btn_load.setProperty("variant", "secondary")
        self.btn_load.clicked.connect(self.tab_can.load_dbc)
        action_layout.addWidget(self.btn_load)

        self.btn_import_motec = QPushButton("📥 Importar XML")
        self.btn_import_motec.setFixedHeight(42)
        self.btn_import_motec.setProperty("variant", "secondary")
        self.btn_import_motec.clicked.connect(self.tab_can.import_xml_config)
        action_layout.addWidget(self.btn_import_motec)

        # General buttons
        self.btn_import = QPushButton("📥 Importar JSON")
        self.btn_import.setFixedHeight(42)
        self.btn_import.setProperty("variant", "secondary")
        self.btn_import.clicked.connect(self.import_json_config)
        action_layout.addWidget(self.btn_import)
        
        self.btn_export = QPushButton("📤 Exportar JSON")
        self.btn_export.setFixedHeight(42)
        self.btn_export.setProperty("variant", "warning")
        self.btn_export.clicked.connect(self.export_json)
        action_layout.addWidget(self.btn_export)

        self.btn_preview = QPushButton("⚙️ Ver Config JSON")
        self.btn_preview.setFixedHeight(42)
        self.btn_preview.setProperty("variant", "info")
        self.btn_preview.setToolTip("Previsualiza el JSON de configuración que se enviará al ESP32")
        self.btn_preview.clicked.connect(self.preview_config)
        action_layout.addWidget(self.btn_preview)

        action_layout.addStretch()

        # Device buttons
        self.btn_download = QPushButton("📥 Leer Config")
        self.btn_download.setFixedHeight(42)
        self.btn_download.setProperty("variant", "accent")
        self.btn_download.clicked.connect(self.download_config)
        self.btn_download.setEnabled(False)
        action_layout.addWidget(self.btn_download)

        self.btn_upload = QPushButton("📤 Enviar Config")
        self.btn_upload.setFixedHeight(42)
        self.btn_upload.setMinimumWidth(130)
        self.btn_upload.setProperty("variant", "success")
        self.btn_upload.clicked.connect(self.upload_config)
        self.btn_upload.setEnabled(False)
        action_layout.addWidget(self.btn_upload)

        self.btn_debug = QPushButton("🐞 Debug")
        self.btn_debug.setFixedHeight(42)
        self.btn_debug.setProperty("variant", "warning")
        self.btn_debug.clicked.connect(self.send_debug_command)
        self.btn_debug.setEnabled(False)
        action_layout.addWidget(self.btn_debug)

        self.btn_factory_reset = QPushButton("🔄 Reset")
        self.btn_factory_reset.setFixedHeight(42)
        self.btn_factory_reset.setProperty("variant", "danger")
        self.btn_factory_reset.setToolTip("Restablecer configuración a valores por defecto")
        self.btn_factory_reset.clicked.connect(self.factory_reset)
        self.btn_factory_reset.setEnabled(False)
        action_layout.addWidget(self.btn_factory_reset)

        parent_layout.addLayout(action_layout)

    def load_stylesheet(self):
        """Load the external QSS stylesheet."""
        qss_path = os.path.join(
            os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
            "assets", "dark_racing.qss"
        )
        if os.path.exists(qss_path):
            with open(qss_path, 'r', encoding='utf-8') as f:
                self.setStyleSheet(f.read())
                self.log_console("[STYLE] Loaded dark_racing.qss")
        else:
            self.log_console("[STYLE] QSS not found, using fallback")
            self._apply_fallback_theme()

    def _apply_fallback_theme(self):
        """Apply fallback dark theme."""
        app = QApplication.instance()
        app.setStyle("Fusion")
        palette = QPalette()
        palette.setColor(QPalette.Window, QColor(26, 26, 26))
        palette.setColor(QPalette.WindowText, Qt.white)
        palette.setColor(QPalette.Base, QColor(30, 30, 30))
        palette.setColor(QPalette.Text, Qt.white)
        palette.setColor(QPalette.Button, QColor(45, 45, 45))
        palette.setColor(QPalette.ButtonText, Qt.white)
        palette.setColor(QPalette.Highlight, QColor(255, 107, 53))
        app.setPalette(palette)

    # ========================================================================
    # REACTIVE UI - 3 MODES ONLY
    # ========================================================================
    
    @Slot(str)
    def on_source_changed(self, mode: str):
        """Handle source mode change. Only 3 valid modes."""
        if mode not in MODE_ALLOWED_SECTIONS:
            self.log_console(f"[ERROR] Invalid mode: {mode}")
            return
        
        self.current_mode = mode
        self.controller.source_mode = mode
        self.update_ui_for_mode(mode)
        self.log_console(f"[MODE] Cambiado a: {mode}")

    @Slot(str)
    def on_controller_mode_changed(self, mode: str):
        """Handle mode change from controller."""
        if self.current_mode != mode:
            self.current_mode = mode
            self.update_ui_for_mode(mode)

    def update_ui_for_mode(self, mode: str):
        """
        Update UI for 3-mode architecture.
        
        CAN_ONLY: Show sensors, hide OBD
        OBD_BRIDGE: Show OBD, hide sensors
        SENSORS_ONLY: Hide both, show Device/Cloud/Live only
        """
        # Update mode badge
        mode_display = {
            "CAN_ONLY": ("🏎️ MODO: CAN", "#ff6b35"),
            "OBD_BRIDGE": ("🔌 MODO: OBD BRIDGE", "#2196f3"),
            "SENSORS_ONLY": ("📍 MODO: TRACKING", "#ffc107"),
        }
        label, color = mode_display.get(mode, ("MODO: ?", "#666"))
        self.lbl_mode_badge.setText(label)
        self.lbl_mode_badge.setStyleSheet(f"""
            background-color: {color};
            color: white;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 14px;
        """)

        # Tab visibility (3 modes)
        if mode == "CAN_ONLY":
            self.tab_widget.setTabVisible(TAB_SENSORS, True)
            self.tab_widget.setTabVisible(TAB_OBD, False)
            self.lbl_obd_status.setVisible(False)
            self.btn_load.setVisible(True)
            self.btn_import_motec.setVisible(True)
            
        elif mode == "OBD_BRIDGE":
            self.tab_widget.setTabVisible(TAB_SENSORS, False)
            self.tab_widget.setTabVisible(TAB_OBD, True)
            self.lbl_obd_status.setVisible(True)
            self.btn_load.setVisible(False)
            self.btn_import_motec.setVisible(False)
            
        elif mode == "SENSORS_ONLY":
            self.tab_widget.setTabVisible(TAB_SENSORS, False)
            self.tab_widget.setTabVisible(TAB_OBD, False)
            self.lbl_obd_status.setVisible(False)
            self.btn_load.setVisible(False)
            self.btn_import_motec.setVisible(False)
        
        # Device, Cloud, Live always visible
        self.tab_widget.setTabVisible(TAB_DEVICE, True)
        self.tab_widget.setTabVisible(TAB_CLOUD, True)
        self.tab_widget.setTabVisible(TAB_LIVE, True)
        
        # Switch to visible tab if current hidden
        if not self.tab_widget.isTabVisible(self.tab_widget.currentIndex()):
            self.tab_widget.setCurrentIndex(TAB_DEVICE)

    # ========================================================================
    # JSON GENERATION - 3 MODES ONLY
    # ========================================================================
    
    def get_config_data(self) -> Dict[str, Any]:
        """
        Generate clean configuration for 3-mode architecture.
        Includes bridge_wifi section for OBD_BRIDGE mode.
        """
        mode = self.current_mode
        allowed = MODE_ALLOWED_SECTIONS.get(mode, set())
        
        config = {"version": "3.2"}
        
        # ===== DEVICE =====
        if "device" in allowed:
            config["device"] = {
                "id": self.tab_device.input_device_id.text() or "NEURONA_001",
                "car_id": self.tab_device.input_car_id.text() or "CAR_001",
                "source": mode
            }
        
        # ===== WIFI (Principal) =====
        if "wifi" in allowed:
            config["wifi"] = {
                "ssid": self.tab_device.input_ssid.text(),
                "password": self.tab_device.input_pass.text()
            }
        
        # ===== CLOUD =====
        if "cloud" in allowed:
            config["cloud"] = {
                "protocol": self.tab_cloud.combo_protocol.currentText() if hasattr(self.tab_cloud, 'combo_protocol') else "mqtt",
                "interval_ms": int(self.tab_cloud.input_cloud_interval.text() or DEFAULT_CLOUD_INTERVAL_MS) if hasattr(self.tab_cloud, 'input_cloud_interval') else DEFAULT_CLOUD_INTERVAL_MS,
                "debug_mode": self.tab_cloud.chk_debug_mode.isChecked() if hasattr(self.tab_cloud, 'chk_debug_mode') else False,
                "mqtt": {
                    "server": self.tab_cloud.input_mqtt_server.text() if hasattr(self.tab_cloud, 'input_mqtt_server') else "",
                    "port": int(self.tab_cloud.input_mqtt_port.text() or 1883) if hasattr(self.tab_cloud, 'input_mqtt_port') else 1883,
                    "user": self.tab_cloud.input_mqtt_user.text() if hasattr(self.tab_cloud, 'input_mqtt_user') else "",
                    "password": self.tab_cloud.input_mqtt_pass.text() if hasattr(self.tab_cloud, 'input_mqtt_pass') else "",
                    "topic": self.tab_cloud.input_mqtt_topic.text() if hasattr(self.tab_cloud, 'input_mqtt_topic') else ""
                }
            }
        
        # ===== SERIAL =====
        if "serial" in allowed:
            config["serial"] = {
                "interval_ms": int(self.tab_device.input_serial_interval.text() or DEFAULT_SERIAL_INTERVAL_MS)
            }
        
        # ===== CAN (CAN_ONLY mode) =====
        if "can" in allowed:
            config["can"] = {
                "enabled": True,
                "cs_pin": self.tab_device.input_cs_pin.value(),
                "int_pin": self.tab_device.input_int_pin.value(),
                "baud_kbps": int(self.tab_device.combo_baud.currentText()),
                "crystal_mhz": int(self.tab_device.combo_crystal.currentText())
            }
        
        # ===== OBD (OBD_BRIDGE mode) =====
        if "obd" in allowed:
            config["obd"] = {
                "enabled": True,
                "mode": "bridge",  # ALWAYS bridge in this mode
                "pids_enabled": self.tab_obd.input_obd_pids.text(),
                "poll_interval_ms": 200,
                "uart": {
                    "rx_pin": self.tab_obd.input_uart_rx.value(),
                    "tx_pin": self.tab_obd.input_uart_tx.value(),
                    "baud": int(self.tab_obd.input_uart_baud.currentText())
                }
            }
        
        # ===== BRIDGE_WIFI (C3 -> ELM327 config, OBD_BRIDGE only) =====
        if "bridge_wifi" in allowed:
            config["bridge_wifi"] = {
                "ssid": self.tab_obd.input_c3_elm_ssid.text(),
                "password": self.tab_obd.input_c3_elm_pass.text(),
                "ip": self.tab_obd.input_c3_elm_ip.text() or "192.168.0.10",
                "port": int(self.tab_obd.input_c3_elm_port.text() or 35000)
            }
        
        # ===== GPS =====
        if "gps" in allowed:
            config["gps"] = {
                "enabled": self.tab_device.chk_gps_enabled.isChecked(),
                "rx_pin": self.tab_device.input_gps_rx_pin.value(),
                "tx_pin": self.tab_device.input_gps_tx_pin.value(),
                "baud": 9600
            }
        
        # ===== IMU =====
        if "imu" in allowed:
            config["imu"] = {
                "enabled": self.tab_device.chk_imu_enabled.isChecked(),
                "sda_pin": 21,
                "scl_pin": 22
            }
        
        # ===== FUEL (OBD_BRIDGE only) =====
        if "fuel" in allowed:
            config["fuel"] = {
                "method": self.tab_obd.combo_fuel_method.currentText(),
                "displacement_l": self.tab_obd.input_engine_disp.value(),
                "volumetric_efficiency": self.tab_obd.input_ve_estimate.value(),
                "air_fuel_ratio": self.tab_obd.input_afr.value()
            }
        
        # ===== SENSORS (CAN_ONLY only) =====
        if "sensors" in allowed:
            config["sensors"] = self._collect_sensors_from_table()
        
        return config

    def _collect_sensors_from_table(self) -> list:
        """Collect sensor data from CAN table."""
        sensors = []
        table = self.tab_can.table
        
        for row in range(table.rowCount()):
            checkbox = table.item(row, Col.ENABLE)
            if checkbox and checkbox.checkState() == Qt.Checked:
                try:
                    id_text = table.item(row, Col.CAN_ID).text().strip()
                    can_id = int(id_text, 16) if id_text.lower().startswith("0x") else int(id_text)
                    
                    name = table.item(row, Col.CHANNEL).text()
                    cloud_id = table.item(row, Col.CLOUD_ID).text().strip() or name
                    offset = int(table.item(row, Col.OFFSET).text())
                    length = int(table.item(row, Col.LENGTH).text())
                    signed = table.item(row, Col.TYPE).text() == "Signed"
                    multiplier = float(table.item(row, Col.MULTIPLIER).text().replace(',', '.'))
                    adder = float(table.item(row, Col.ADDER).text().replace(',', '.'))
                    
                    byte_order_item = table.item(row, Col.BYTE_ORDER)
                    byte_order = byte_order_item.data(Qt.UserRole) or "big_endian"
                    
                    sensors.append({
                        "name": name,
                        "cloud_id": cloud_id,
                        "can_id": can_id,
                        "start_byte": offset,
                        "length": length * 8,
                        "signed": signed,
                        "multiplier": multiplier,
                        "offset": adder,
                        "big_endian": byte_order == "big_endian",
                        "enabled": True
                    })
                except (ValueError, AttributeError) as e:
                    self.log_console(f"[WARN] Skipping row {row}: {e}")
        
        return sensors

    # ========================================================================
    # CONNECTION & SERIAL
    # ========================================================================
    
    def refresh_ports(self):
        ports = self.serial_manager.list_ports()
        self.combo_ports.clear()
        self.combo_ports.addItems(ports)
        
    def toggle_connection(self):
        if self.btn_connect.text().endswith("Conectar"):
            port = self.combo_ports.currentText()
            if not port:
                QMessageBox.warning(self, "Error", "Selecciona un puerto primero.")
                return
            
            success, msg = self.serial_manager.connect(port)
            if success:
                self._set_connected_state(True, port)
                self.log_console(f"[SERIAL] Conectado a {port}")
                QApplication.processEvents()
                time.sleep(0.3)
                self.download_config()
            else:
                QMessageBox.critical(self, "Error de Conexión", msg)
        else:
            success, msg = self.serial_manager.disconnect()
            self._set_connected_state(False)
            self.log_console("[SERIAL] Desconectado")

    def _set_connected_state(self, connected: bool, port: str = ""):
        if connected:
            self.btn_connect.setText("🔌 Desconectar")
            self.btn_connect.setProperty("variant", "danger")
            self.lbl_connection_status.setText("🟢 CONECTADO")
            self.lbl_connection_status.setStyleSheet("""
                background-color: #00e676; color: #1a1a1a;
                padding: 8px 16px; border-radius: 12px; font-weight: bold; font-size: 14px;
            """)
            self.lbl_port_info.setText(f"Puerto: {port}")
            self.btn_upload.setEnabled(True)
            self.btn_download.setEnabled(True)
            self.btn_debug.setEnabled(True)
            self.btn_monitor.setEnabled(True)
            self.btn_factory_reset.setEnabled(True)
        else:
            self.btn_connect.setText("🔌 Conectar")
            self.btn_connect.setProperty("variant", "success")
            self.lbl_connection_status.setText("⚪ DESCONECTADO")
            self.lbl_connection_status.setStyleSheet("""
                background-color: #404040; color: #b0b0b0;
                padding: 8px 16px; border-radius: 12px; font-weight: bold; font-size: 14px;
            """)
            self.lbl_port_info.setText("Puerto: --")
            self.btn_upload.setEnabled(False)
            self.btn_download.setEnabled(False)
            self.btn_debug.setEnabled(False)
            self.btn_monitor.setEnabled(False)
            self.btn_factory_reset.setEnabled(False)
            self.monitoring = False
            self.btn_monitor.setText("▶ Monitor")
        
        self.btn_connect.style().unpolish(self.btn_connect)
        self.btn_connect.style().polish(self.btn_connect)

    def toggle_monitoring(self):
        if not self.serial_manager.is_connected:
            return
        
        if not self.monitoring:
            self.serial_manager.write("LIVE_ON")
            self.monitoring = True
            self.btn_monitor.setText("⏹ Stop")
            self.btn_monitor.setProperty("variant", "danger")
            self.log_console("[LIVE] Monitoring started")
            self.tab_widget.setCurrentWidget(self.tab_live)
        else:
            self.serial_manager.write("LIVE_OFF")
            self.monitoring = False
            self.btn_monitor.setText("▶ Monitor")
            self.btn_monitor.setProperty("variant", "primary")
            self.log_console("[LIVE] Monitoring stopped")
        
        self.btn_monitor.style().unpolish(self.btn_monitor)
        self.btn_monitor.style().polish(self.btn_monitor)

    def toggle_console(self):
        show = not self.console_widget.isVisible()
        self.console_widget.setVisible(show)
        self.splitter.setSizes([700, 200] if show else [1, 0])

    def log_console(self, text: str):
        timestamp = datetime.now().strftime("%H:%M:%S")
        self.console_widget.log(f"[{timestamp}] {text}")

    # ========================================================================
    # DATA UPDATE
    # ========================================================================
    
    def update_data(self):
        if not self.simulation_active and self.serial_manager.is_connected:
            lines = self.serial_manager.read_all_lines()
            if lines:
                latest_values = {}
                for line in lines:
                    line = line.strip()
                    if not line: continue
                    
                    # 1. Handle JSON Telemetry (line starts with {)
                    if line.startswith("{"):
                        try:
                            data = json.loads(line)
                            if isinstance(data, dict) and "s" in data:
                                latest_values.update(data["s"])
                                
                                # Update C3 bridge status (OBD_Status is float: 1.0 or 0.0)
                                if "OBD_Status" in data["s"]:
                                    status = data["s"]["OBD_Status"]
                                    if status == 1.0:
                                        self.lbl_obd_status.setText("C3: ✓")
                                        self.lbl_obd_status.setStyleSheet("""
                                            background-color: #00e676; color: #1a1a1a;
                                            padding: 8px 16px; border-radius: 12px; font-weight: bold;
                                        """)
                                    else:
                                        self.lbl_obd_status.setText("C3: ✗")
                                        self.lbl_obd_status.setStyleSheet("""
                                            background-color: #ff5252; color: white;
                                            padding: 8px 16px; border-radius: 12px; font-weight: bold;
                                        """)
                        except json.JSONDecodeError:
                            pass

                    # 2. Handle Explicit Responses (RSP:, STATUS:, DIAG:)
                    elif line.startswith("STATUS:"):
                        self.log_console(f"[DEVICE]: {line}")
                    elif line.startswith("DIAG:"):
                        self.log_console(f"[DEVICE]: {line}") 
                    elif line.startswith("RSP:"):
                        self.log_console(f"[DEVICE]: {line}")
                    # 3. Handle Debug/Info messages
                    elif any(tag in line for tag in ["[OBD_BRIDGE]", "[MAIN]", "[CLOUD]", "[WIFI]", "[CONFIG]"]):
                         self.log_console(f"[LOG]: {line}")
                
                if latest_values:
                    self.tab_live.update_values(latest_values)

    # ========================================================================
    # CONFIG UPLOAD/DOWNLOAD
    # ========================================================================
    
    def upload_config(self):
        if not self.serial_manager.is_connected:
            QMessageBox.warning(self, "Error", "No hay conexión serial.")
            return
        
        config = self.get_config_data()
        
        # Calculate size just for user warning (optional)
        json_str = json.dumps(config, separators=(',', ':'))
        size_bytes = len(json_str.encode('utf-8'))
        if size_bytes > 4000:
            QMessageBox.warning(self, "Config Grande", 
                f"Configuración muy grande ({size_bytes} bytes). Máximo sugerido 3500.")
        
        self.log_console(f"[CONFIG] Delegando subida a SerialManager ({size_bytes} bytes)...")
        QApplication.processEvents()
        
        # Use the SerialManager's robust send logic
        success, msg = self.serial_manager.send_config(config)
        
        if success:
            QMessageBox.information(self, "Éxito", "✓ Configuración enviada y guardada")
            self.log_console(f"[CONFIG] {msg}")
        else:
            QMessageBox.critical(self, "Error de Subida", msg)
            self.log_console(f"[CONFIG] Error: {msg}")

    def download_config(self):
        if not self.serial_manager.is_connected:
            return
        
        self.log_console("[CONFIG] Solicitando configuración...")
        # Protocol v3: "GET_CONFIG" (no CMD:)
        self.serial_manager.write("GET_CONFIG")
        QApplication.processEvents()
        time.sleep(1.5)
        
        lines = self.serial_manager.read_all_lines()
        if not lines:
            self.log_console("[CONFIG] No se recibieron datos")
            return

        for line in lines:
            clean_line = line.strip()
            # Firmware sends "CONFIG:{...}"
            if "CONFIG:{" in clean_line:
                json_part = clean_line.split("CONFIG:", 1)[1]
                try:
                    config = json.loads(json_part)
                    self._apply_config_to_ui(config)
                    self.log_console("[CONFIG] ✓ Configuración cargada")
                    return
                except json.JSONDecodeError as e:
                    self.log_console(f"[CONFIG] Error JSON: {e}")
            # Fallback for raw JSON
            elif clean_line.startswith("{"):
                try:
                    config = json.loads(clean_line)
                    self._apply_config_to_ui(config)
                    self.log_console("[CONFIG] ✓ Configuración cargada (Raw)")
                    return
                except:
                    pass
        
        self.log_console("[CONFIG] No se encontró JSON válido")

    def _apply_config_to_ui(self, config: dict):
        if "device" in config:
            dev = config["device"]
            self.tab_device.input_device_id.setText(dev.get("id", ""))
            self.tab_device.input_car_id.setText(dev.get("car_id", ""))
            source = dev.get("source", "CAN_ONLY")
            # Validate against 3-mode architecture
            if source not in MODE_ALLOWED_SECTIONS:
                source = "CAN_ONLY"
            self.tab_device.set_source_value_by_code(source)
            self.on_source_changed(source)
        
        if "wifi" in config:
            self.tab_device.input_ssid.setText(config["wifi"].get("ssid", ""))
            self.tab_device.input_pass.setText(config["wifi"].get("password", ""))
        
        if "cloud" in config:
            cloud = config["cloud"]
            # Set Cloud Interval in Cloud Tab (not Device Tab)
            if "interval_ms" in cloud and hasattr(self.tab_cloud, 'input_cloud_interval'):
                self.tab_cloud.input_cloud_interval.setText(str(cloud["interval_ms"]))
            
            # Set Protocol
            proto = cloud.get("protocol", "mqtt")
            if hasattr(self.tab_cloud, 'combo_protocol'):
                idx = self.tab_cloud.combo_protocol.findText(proto)
                if idx >= 0: self.tab_cloud.combo_protocol.setCurrentIndex(idx)
            
            # Set Debug Mode
            if hasattr(self.tab_cloud, 'chk_debug_mode'):
                self.tab_cloud.chk_debug_mode.setChecked(cloud.get("debug_mode", False))
                
            # MQTT Settings
            if "mqtt" in cloud and hasattr(self.tab_cloud, 'input_mqtt_server'):
                mq = cloud["mqtt"]
                self.tab_cloud.input_mqtt_server.setText(mq.get("server", ""))
                self.tab_cloud.input_mqtt_port.setText(str(mq.get("port", 1883)))
                self.tab_cloud.input_mqtt_user.setText(mq.get("user", ""))
                self.tab_cloud.input_mqtt_pass.setText(mq.get("password", ""))
                self.tab_cloud.input_mqtt_topic.setText(mq.get("topic", ""))
                
            # HTTP Settings
            if "http" in cloud and hasattr(self.tab_cloud, 'input_http_url'):
                self.tab_cloud.input_http_url.setText(cloud["http"].get("url", ""))

        if "gps" in config:
            self.tab_device.chk_gps_enabled.setChecked(config["gps"].get("enabled", False))
        
        if "imu" in config:
            self.tab_device.chk_imu_enabled.setChecked(config["imu"].get("enabled", False))
        
        # Load bridge_wifi into OBD tab
        if "bridge_wifi" in config:
            bw = config["bridge_wifi"]
            self.tab_obd.input_c3_elm_ssid.setText(bw.get("ssid", ""))
            self.tab_obd.input_c3_elm_pass.setText(bw.get("password", ""))
            self.tab_obd.input_c3_elm_ip.setText(bw.get("ip", "192.168.0.10"))
            self.tab_obd.input_c3_elm_port.setText(str(bw.get("port", 35000)))
        
        self.log_console(f"[CONFIG] Modo actual: {self.current_mode}")

    # ========================================================================
    # IMPORT/EXPORT
    # ========================================================================
    
    def import_json_config(self):
        file_path, _ = QFileDialog.getOpenFileName(self, "Importar", "", "JSON (*.json)")
        if not file_path:
            return
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                config = json.load(f)
            self._apply_config_to_ui(config)
            QMessageBox.information(self, "Importado", f"✓ Configuración importada")
        except Exception as e:
            QMessageBox.critical(self, "Error", f"Error: {e}")

    def export_json(self):
        file_path, _ = QFileDialog.getSaveFileName(
            self, "Exportar", f"neurona_{self.current_mode}.json", "JSON (*.json)"
        )
        if not file_path:
            return
        try:
            config = self.get_config_data()
            with open(file_path, 'w', encoding='utf-8') as f:
                json.dump(config, f, indent=2, ensure_ascii=False)
            QMessageBox.information(self, "Exportado", f"✓ Guardado: {file_path}")
            self.log_console(f"[EXPORT] {file_path}")
        except Exception as e:
            QMessageBox.critical(self, "Error", f"Error: {e}")

    def preview_config(self):
        """
        Previsualiza el JSON de CONFIGURACIÓN que se enviará al ESP32.
        NOTA: Este NO es el payload MQTT de telemetría hacia la nube.
        """
        config = self.get_config_data()
        json_str = json.dumps(config, indent=2, ensure_ascii=False)
        size = len(json.dumps(config).encode('utf-8'))
        
        dialog = QDialog(self)
        dialog.setWindowTitle(f"CONFIG JSON - {self.current_mode} ({size} bytes)")
        dialog.resize(750, 650)
        
        layout = QVBoxLayout(dialog)
        
        # Info header
        info_label = QLabel("""
            <p><b>📋 CONFIG JSON</b> - Este JSON se envía al ESP32 para configurarlo.</p>
            <p style="color: #ff9800;"><b>⚠️ NOTA:</b> Este NO es el payload MQTT que va a la nube.
            El payload de telemetría lo genera el firmware automáticamente.</p>
        """)
        info_label.setStyleSheet("background: #2a2a2a; padding: 10px; border-radius: 6px;")
        layout.addWidget(info_label)
        
        stats = QLabel(f"<b>Modo:</b> {self.current_mode} | <b>Tamaño:</b> {size} bytes | <b>Secciones:</b> {len(config)} keys")
        layout.addWidget(stats)
        
        text_view = QTextEdit()
        text_view.setPlainText(json_str)
        text_view.setReadOnly(True)
        text_view.setStyleSheet("background: #1a1a1a; color: #00ff88; font-family: Consolas, monospace; font-size: 12px;")
        layout.addWidget(text_view)
        
        dialog.exec()

    def check_wifi_status(self):
        if self.serial_manager.is_connected:
            self.serial_manager.write("GET_STATUS")
            self.log_console("[CMD] GET_STATUS (Wifi/Cloud info)")
        else:
            QMessageBox.warning(self, "Error", "No conectado.")

    def show_obd_payload_preview(self):
        """Show a simulation of the MQTT payload based on current configuration."""
        self.preview_mqtt_payload()

    def preview_mqtt_payload(self):
        """
        Generates and displays a SIMULATION of the MQTT payload that the firmware
        would build based on the current configuration.
        """
        payload = self._simulate_mqtt_payload()
        json_str = json.dumps(payload, indent=2, ensure_ascii=False)
        size = len(json.dumps(payload).encode('utf-8'))
        
        dialog = QDialog(self)
        dialog.setWindowTitle(f"MQTT PAYLOAD PREVIEW - {self.current_mode} (~{size} bytes)")
        dialog.resize(600, 700)
        
        layout = QVBoxLayout(dialog)
        
        info = QLabel("""
            <p style="font-size: 14px; font-weight: bold;">📡 SIMULACIÓN DE PAYLOAD MQTT</p>
            <p>Este es el JSON exacto que el Firmware construye y envía a la nube.</p>
            <p style="color: #00e676;">✓ Incluye mapeo de PIDs a nombres estándar (MoTeC format)</p>
        """)
        info.setStyleSheet("background: #2a2a2a; padding: 10px; border-radius: 6px;")
        layout.addWidget(info)
        
        text = QTextEdit()
        text.setPlainText(json_str)
        text.setReadOnly(True)
        text.setStyleSheet("font-family: Consolas; font-size: 13px; color: #4fc3f7; background: #121212;")
        layout.addWidget(text)
        
        layout.addWidget(QPushButton("Cerrar", clicked=dialog.accept))
        dialog.exec()

    def _simulate_mqtt_payload(self) -> dict:
        """Mimies CloudManager::buildPayload logic."""
        config = self.get_config_data()
        
        # Base structure from CloudManager.cpp
        payload = {
            "id": config.get("device", {}).get("id", "NEURONA_DEV"),
            "idc": config.get("device", {}).get("car_id", "CAR_001"),
            "d": config.get("cloud", {}).get("debug_mode", False),
            "dt": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "s": {},
            "DTC": []
        }
        
        s = payload["s"]
        
        # 1. Simulate OBD Sensors (if in OBD_BRIDGE mode)
        if self.current_mode == "OBD_BRIDGE":
            pids_str = self.tab_obd.input_obd_pids.text().upper()
            enabled_pids = [p.strip() for p in pids_str.split(',') if p.strip()]
            
            # Mapping from SourceOBDBridge/CloudManager
            # PID -> JSON Key (UPDATED to PIDs as requested)
            pid_map = {
                "0X0C": "0x0C", # RPM
                "0X0D": "0x0D", # Speed
                "0X05": "0x05", # Coolant
                "0X11": "0x11", # TPS
                "0X04": "0x04", # Load
                "0X10": "0x10", # MAF
                "0X0B": "0x0B", # MAP
                "0X5C": "0x5C", # Oil Temp
                "0X2F": "0x2F", # Fuel Level
                "0X5E": "0x5E", # Fuel Rate
                "BAT":  "0x42", # Control Module Voltage
                "0X42": "0x42"
            }
            
            # Add dummy values for enabled PIDs
            for pid in enabled_pids:
                # Normalize 0x0C to 0X0C
                pid_norm = pid.upper()
                if pid_norm in pid_map:
                    key = pid_map[pid_norm]
                    s[key] = {"v": 1234.5} # Dummy value
                else:
                    pass
                    
            # Always present keys if data exists (simulated)
            if not "0x42" in s: s["0x42"] = {"v": 12.5}

        # 2. Simulate GPS (if enabled) - Values NOT changed to PIDs
        if config.get("gps", {}).get("enabled", False):
            s["lat"] = {"v": "-33.4489"}
            s["lng"] = {"v": "-70.6693"}
            s["vel_kmh"] = {"v": 0.0}
            s["alt_m"] = {"v": 520.0}
            s["rumbo"] = {"v": 0}
            s["gps_sats"] = {"v": 8}

        # 3. Simulate IMU (if enabled)
        if config.get("imu", {}).get("enabled", False):
            s["accel_x"] = {"v": 0.01}
            s["accel_y"] = {"v": 0.02}
            s["accel_z"] = {"v": 0.98}
            s["gyro_x"] = {"v": 0.0}
            s["gyro_y"] = {"v": 0.0}
            s["gyro_z"] = {"v": 0.0}
            
        # 4. Meta
        s["wifi_rssi"] = {"v": -65}
        s["heap_free"] = {"v": 150000}
        
        return payload

    def send_debug_command(self):
        if self.serial_manager.is_connected:
            self.serial_manager.write("GET_DIAG")
            self.log_console("[CMD] GET_DIAG")

    def factory_reset(self):
        """Send FACTORY_RESET to ESP32 to restore default configuration."""
        if not self.serial_manager.is_connected:
            QMessageBox.warning(self, "Error", "No hay conexión serial.")
            return
        
        reply = QMessageBox.question(
            self, "Confirmar Reset",
            "¿Restablecer configuración a valores por defecto?\n\n"
            "Esto cambiará:\n"
            "• cloud_interval_ms → 100ms\n"
            "• source → CAN_ONLY\n"
            "• Todos los parámetros a defaults\n\n"
            "El dispositivo se reiniciará.",
            QMessageBox.Yes | QMessageBox.No,
            QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            self.serial_manager.write("FACTORY_RESET")
            self.log_console("[CMD] FACTORY_RESET enviado")
            QApplication.processEvents()
            time.sleep(0.5)
            
            # Read response
            lines = self.serial_manager.read_all_lines()
            for line in lines:
                self.log_console(f"[DEVICE]: {line.strip()}")
            
            QMessageBox.information(self, "Reset", "✓ Configuración restablecida.\nReconecta después de que reinicie.")
