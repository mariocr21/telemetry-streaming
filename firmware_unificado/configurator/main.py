import sys
import os
from PySide6.QtWidgets import (QApplication, QMainWindow, QLabel, QVBoxLayout, QWidget,
                               QPushButton, QFileDialog, QTableWidget, QTableWidgetItem, QHeaderView, QHBoxLayout,
                               QTabWidget, QFormLayout, QLineEdit, QCheckBox, QGroupBox, QTextEdit, QSplitter,
                               QDialog, QDialogButtonBox, QSpinBox, QDoubleSpinBox, QScrollArea)
from PySide6.QtCore import Qt, QTimer, QThread, Signal, Slot
from PySide6.QtGui import QFont, QPalette, QColor, QIcon, QPixmap

# Import local modules
from dbc_parser import DBCParser
from json_generator import JSONGenerator
from serial_manager import SerialManager
from serial_worker import SerialWorker
from xml_loader import XmlLoader
import json
import random
from PySide6.QtWidgets import QComboBox, QMessageBox
 
# =====================================================
# CONFIGURACIÓN DE INTERVALOS EN TIEMPO REAL
# =====================================================
# Intervalo de actualización de la UI en milisegundos.
# Valores más bajos = mayor reactividad pero más carga de CPU.
# Recomendado: 30-50ms para telemetría de ECU.
UI_REFRESH_INTERVAL_MS = 30

# Intervalo por defecto para subida a la nube (ms).
# Este valor se usa como default en Device Settings.
# El firmware usará este valor para enviar datos al servidor.
DEFAULT_CLOUD_INTERVAL_MS = 1000

# Intervalo para telemetría serial ESP32->PC (ms).
# Define cada cuánto el ESP32 envía datos por serial.
# Valores más bajos = datos más frescos pero más tráfico serial.
DEFAULT_SERIAL_INTERVAL_MS = 30

# =====================================================
# COLUMN INDICES - SINGLE SOURCE OF TRUTH
# Prevents bugs from hardcoded column numbers
# =====================================================
class Col:
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

class MainWindow(QMainWindow):
    def __init__(self):
        super().__init__()

        self.setWindowTitle("Neurona Configurator - MOTEC/CAN")
        self.resize(1200, 800)
        
        self.parser = DBCParser()
        self.serial_manager = SerialManager()
        
        self.simulation_active = False
        self.timer = QTimer()
        self.timer.timeout.connect(self.update_data)
        self.timer.start(UI_REFRESH_INTERVAL_MS)  # Optimizado para real-time

        # Apply Dark Theme
        self.apply_dark_theme()
        self.apply_global_styles()


        # Central Widget
        central_widget = QWidget()
        self.setCentralWidget(central_widget)
        main_layout = QVBoxLayout(central_widget)
        main_layout.setContentsMargins(10, 10, 10, 10)
        main_layout.setSpacing(10)

        # Header
        header_layout = QHBoxLayout()
        
        # Logo
        logo_label = QLabel()
        # Try to load logo from current directory or script directory
        logo_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "neurona-logo.png")
        if not os.path.exists(logo_path):
            logo_path = "neurona-logo.png"
            
        pixmap = QPixmap(logo_path)
        if not pixmap.isNull():
            pixmap = pixmap.scaledToHeight(60, Qt.SmoothTransformation)
            logo_label.setPixmap(pixmap)
            header_layout.addWidget(logo_label)
            header_layout.addSpacing(15)


        
        # OBD Status Indicator (added for user visibility)
        self.lbl_obd_status = QLabel("OBD: N/A")
        self.lbl_obd_status.setStyleSheet("background-color: #444; color: #aaa; padding: 6px 10px; border-radius: 6px; font-weight: bold; margin-left: 15px;")
        header_layout.addWidget(self.lbl_obd_status)

        header_layout.addStretch()

        # Serial Controls (moved to header for better hierarchy)
        self.combo_ports = QComboBox()
        self.combo_ports.setMinimumWidth(140)
        self.combo_ports.setFixedHeight(32)
        header_layout.addWidget(self.combo_ports)

        self.btn_refresh = QPushButton("↻")
        self.btn_refresh.setFixedSize(36, 32)
        self.btn_refresh.clicked.connect(self.refresh_ports)
        self.set_button_variant(self.btn_refresh, "ghost")
        header_layout.addWidget(self.btn_refresh)

        self.btn_connect = QPushButton("Conectar")
        self.btn_connect.setFixedSize(110, 32)
        self.set_button_variant(self.btn_connect, "secondary")
        self.btn_connect.clicked.connect(self.toggle_connection)
        header_layout.addWidget(self.btn_connect)

        self.btn_toggle_console = QPushButton("Consola")
        self.btn_toggle_console.setFixedSize(90, 32)
        self.set_button_variant(self.btn_toggle_console, "ghost")
        self.btn_toggle_console.clicked.connect(self.toggle_console)
        header_layout.addWidget(self.btn_toggle_console)
        
        # New Monitor Button
        self.btn_monitor = QPushButton("▶ Monitor")
        self.btn_monitor.setFixedSize(90, 32)
        self.set_button_variant(self.btn_monitor, "success")
        self.btn_monitor.clicked.connect(self.toggle_monitoring)
        self.btn_monitor.setEnabled(False) # Disabled until connected
        header_layout.addWidget(self.btn_monitor)

        header_layout.addSpacing(10)

        self.status_label = QLabel("Listo")
        self.status_label.setStyleSheet("color: #aaaaaa;")
        header_layout.addWidget(self.status_label)

        main_layout.addLayout(header_layout)

        # Create a Splitter for Tab Content and Serial Console
        self.splitter = QSplitter(Qt.Vertical)
        main_layout.addWidget(self.splitter)

        # Tabs Container (el estilo lo maneja el QSS global)
        self.tab_widget = QTabWidget()
        self.splitter.addWidget(self.tab_widget)

        # Tab 1: CAN Mapping
        self.tab_can = QWidget()
        self.setup_can_tab()
        self.tab_widget.addTab(self.tab_can, "Sensores")

        # Serial Console (Bottom part of splitter) - collapsible
        self.console_widget = QWidget()
        console_layout = QVBoxLayout(self.console_widget)
        console_layout.setContentsMargins(0, 0, 0, 0)

        console_header = QHBoxLayout()
        console_header.addWidget(QLabel("Consola serial:"))

        self.btn_clear_console = QPushButton("Limpiar")
        self.btn_clear_console.setFixedSize(70, 26)
        self.btn_clear_console.clicked.connect(self.clear_console)
        console_header.addWidget(self.btn_clear_console)
        console_header.addStretch()

        console_layout.addLayout(console_header)

        self.console_output = QTextEdit()
        self.console_output.setReadOnly(True)
        self.console_output.setStyleSheet("background-color: #1e1e1e; color: #00ff00; font-family: Consolas, Monospace;")
        console_layout.addWidget(self.console_output)

        self.splitter.addWidget(self.console_widget)
        self.console_widget.setVisible(False)  # start collapsed
        self.splitter.setStretchFactor(0, 4)
        self.splitter.setStretchFactor(1, 1)

        # Tab 2: Device Settings
        self.tab_settings = QWidget()
        self.setup_settings_tab()
        self.tab_widget.addTab(self.tab_settings, "Dispositivo")

        # Tab 3: Cloud Settings
        self.tab_cloud = QWidget()
        self.setup_cloud_tab()
        self.tab_widget.addTab(self.tab_cloud, "Nube")

        # Tab 4: OBD Settings (nuevo)
        self.tab_obd = QWidget()
        self.setup_obd_tab()
        self.tab_widget.addTab(self.tab_obd, "OBD")

        # Tab 5: Live Data (New Visualization Logic)
        self.tab_live = QWidget()
        self.setup_live_tab()
        self.tab_widget.addTab(self.tab_live, "En vivo")

        # Toolbar / Actions (Bottom)
        action_layout = QHBoxLayout()
        
        self.btn_load = QPushButton("Cargar DBC")
        self.btn_load.setFixedHeight(40)
        self.set_button_variant(self.btn_load, "primary")
        self.btn_load.clicked.connect(self.load_dbc)
        action_layout.addWidget(self.btn_load)

        self.btn_import_motec = QPushButton("Importar XML")
        self.btn_import_motec.setFixedHeight(40)
        self.set_button_variant(self.btn_import_motec, "accent")
        self.btn_import_motec.clicked.connect(self.import_xml_config)
        action_layout.addWidget(self.btn_import_motec)

        self.btn_import = QPushButton("Importar JSON")
        self.btn_import.setFixedHeight(40)
        self.set_button_variant(self.btn_import, "secondary")
        self.btn_import.clicked.connect(self.import_json_config)
        action_layout.addWidget(self.btn_import)
        
        # Nota: "Añadir sensor" ahora vive en la toolbar del tab CAN (mejor contexto).

        self.btn_export = QPushButton("Exportar JSON")
        self.btn_export.setFixedHeight(40)
        self.set_button_variant(self.btn_export, "warning")
        self.btn_export.clicked.connect(self.export_json)
        action_layout.addWidget(self.btn_export)

        self.btn_preview = QPushButton("Ver Payload")
        self.btn_preview.setFixedHeight(40)
        self.set_button_variant(self.btn_preview, "success")
        self.btn_preview.clicked.connect(self.preview_payload)
        action_layout.addWidget(self.btn_preview)

        self.btn_simulate = QPushButton("Simular")
        self.btn_simulate.setFixedHeight(40)
        self.set_button_variant(self.btn_simulate, "accent")
        self.btn_simulate.clicked.connect(self.toggle_simulation)
        action_layout.addWidget(self.btn_simulate)
        
        action_layout.addStretch()

        self.btn_upload = QPushButton("Enviar Config")
        self.btn_upload.setFixedHeight(40)
        self.set_button_variant(self.btn_upload, "info")
        self.btn_upload.clicked.connect(self.upload_config)
        self.btn_upload.setEnabled(False)
        action_layout.addWidget(self.btn_upload)

        # Download Config Button (GET CONFIG from ESP32)
        self.btn_download = QPushButton("📥 Obtener Config")
        self.btn_download.setFixedHeight(40)
        self.set_button_variant(self.btn_download, "accent")
        self.btn_download.clicked.connect(self.download_config)
        self.btn_download.setEnabled(False)
        action_layout.addWidget(self.btn_download)

        # Memory Stats Button
        self.btn_memory = QPushButton("📊 Memoria")
        self.btn_memory.setFixedHeight(40)
        self.set_button_variant(self.btn_memory, "secondary")
        self.btn_memory.clicked.connect(self.show_memory_stats)
        self.btn_memory.setEnabled(False)
        action_layout.addWidget(self.btn_memory)

        # Debug/Diagnostics Button
        self.btn_debug = QPushButton("🐞 Diagnóstico")
        self.btn_debug.setFixedHeight(40)
        self.set_button_variant(self.btn_debug, "warning")
        self.btn_debug.clicked.connect(self.send_debug_command)
        self.btn_debug.setEnabled(False)
        action_layout.addWidget(self.btn_debug)

        main_layout.addLayout(action_layout)

        # Trigger initial UI state update (after all tabs are created)
        if hasattr(self, "combo_source"):
             self.on_source_changed_ui(self.combo_source.currentText())

        # Initial port refresh
        self.refresh_ports()

    def preview_payload(self):
        # Gather selected sensors with their Cloud IDs
        selected_sensors = []  # List of tuples (name, cloud_id)
        for row in range(self.table.rowCount()):
            checkbox_item = self.table.item(row, 0)
            if checkbox_item.checkState() == Qt.Checked:
                signal_name = self.table.item(row, 2).text()  # Channel col 2
                cloud_id = self.table.item(row, 3).text().strip()  # Cloud ID col 3
                if not cloud_id:
                    cloud_id = signal_name  # Fallback to name
                selected_sensors.append((signal_name, cloud_id))
        
        if not selected_sensors:
            QMessageBox.warning(self, "Warning", "No sensors selected.")
            return

        # Construct payload with NEW UNIFIED FORMAT
        from datetime import datetime
        protocol = self.combo_protocol.currentText()
        
        # ================================================================
        # NEW UNIFIED PAYLOAD FORMAT (same for HTTP and MQTT)
        # ================================================================
        # d = true: NO guardar en BD (debug)
        # d = false: SÍ guardar en BD (producción)
        debug_mode = self.chk_debug_mode.isChecked()
        
        payload = {
            "id": self.input_device_id.text() or "CANAM_FREZAMALA",
            "idc": self.input_car_id.text() or "CAM20200000002",
            "dt": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "d": debug_mode,  # Debug flag from checkbox
            "s": {},
            "DTC": []
        }
        
        # Add sensors with the new format: "cloud_id": {"v": value}
        for name, cloud_id in selected_sensors:
            payload["s"][cloud_id] = {"v": 123.45}
        
        # Add GPS sensors if GPS is enabled
        if self.chk_gps_enabled.isChecked():
            payload["s"]["lat"] = {"v": 25.123456}
            payload["s"]["lng"] = {"v": -100.987654}
            payload["s"]["vel_kmh"] = {"v": 85.5}
            payload["s"]["alt_m"] = {"v": 1850.0}
            payload["s"]["rumbo"] = {"v": 45.2}
            payload["s"]["gps_sats"] = {"v": 9}
            
        json_str = json.dumps(payload, indent=2)
        size_bytes = len(json.dumps(payload).encode('utf-8'))  # Size without indent
        
        # Show Dialog
        from PySide6.QtWidgets import QDialog, QTextEdit
        dialog = QDialog(self)
        dialog.setWindowTitle(f"Cloud Payload Preview ({size_bytes} bytes)")
        dialog.resize(600, 500)
        layout = QVBoxLayout(dialog)
        
        # MQTT Connection Info
        mqtt_info = ""
        if protocol == "mqtt":
            mqtt_info = (
                f"<br><b>MQTT Broker:</b> {self.input_mqtt_server.text()}<br>"
                f"<b>MQTT Port:</b> {self.input_mqtt_port.text()}<br>"
                f"<b>MQTT User:</b> {self.input_mqtt_user.text()}<br>"
                f"<b>MQTT Topic:</b> {self.input_mqtt_topic.text()}<br>"
            )
        
        # GPS Info
        gps_info = ""
        if self.chk_gps_enabled.isChecked():
            gps_info = "<br><b>GPS:</b> HABILITADO (+6 sensores: lat, lng, vel_kmh, alt_m, rumbo, gps_sats)"
        else:
            gps_info = "<br><b>GPS:</b> Deshabilitado"
        
        msg = (
            f"<b>Estimated Payload Size:</b> {size_bytes} bytes<br>"
            f"<b>Sensor Count:</b> {len(selected_sensors)}<br>"
            f"<b>Protocol:</b> {protocol.upper()}<br>"
            f"<b>Device ID:</b> {payload.get('id', 'N/A')}<br>"
            f"<b>Car ID:</b> {payload.get('idc', 'N/A')}<br>"
            f"{mqtt_info}"
            f"{gps_info}"
            f"<br><b>Format:</b> Unified Neurona Telemetry Format"
        )
        
        info_label = QLabel(msg)
        layout.addWidget(info_label)
        
        text_edit = QTextEdit()
        text_edit.setPlainText(json_str)
        text_edit.setReadOnly(True)
        text_edit.setStyleSheet("font-family: Consolas, Monospace; font-size: 12px;")
        layout.addWidget(text_edit)
        
        dialog.exec()

    def setup_can_tab(self):
        layout = QVBoxLayout(self.tab_can)

        # ===== Toolbar local (reduce fricción cognitiva) =====
        top_bar = QHBoxLayout()
        top_bar.setSpacing(10)

        title = QLabel("Sensores / CAN Mapping")
        title.setStyleSheet("font-weight: 600; color: #eaeaea;")
        top_bar.addWidget(title)

        top_bar.addStretch()

        self.input_can_search = QLineEdit()
        self.input_can_search.setPlaceholderText("Buscar (ID / Channel / Cloud ID)...")
        self.input_can_search.setFixedHeight(32)
        self.input_can_search.textChanged.connect(self.filter_can_table)
        self.input_can_search.setStyleSheet("""
            QLineEdit {
                background-color: #1e1e1e;
                border: 1px solid #444;
                border-radius: 6px;
                padding: 6px 10px;
                color: #eee;
            }
            QLineEdit:focus { border: 1px solid #00ffcc; }
        """)
        top_bar.addWidget(self.input_can_search)

        self.chk_can_advanced = QCheckBox("Avanzado")
        self.chk_can_advanced.setToolTip("Muestra/oculta columnas avanzadas de Motec (offset, mask, byte order, etc.)")
        self.chk_can_advanced.stateChanged.connect(lambda _: self.set_can_columns_mode(self.chk_can_advanced.isChecked()))
        top_bar.addWidget(self.chk_can_advanced)

        self.btn_add_manual = QPushButton("Añadir sensor")
        self.btn_add_manual.setFixedHeight(32)
        self.btn_add_manual.setStyleSheet("background-color: #8e44ad; color: white; border-radius: 6px; padding: 0 12px;")
        self.btn_add_manual.clicked.connect(self.add_manual_sensor_dialog)
        top_bar.addWidget(self.btn_add_manual)

        self.btn_select_all = QPushButton("Seleccionar todo")
        self.btn_select_all.setFixedHeight(32)
        self.btn_select_all.clicked.connect(lambda: self.set_all_checked(True))
        top_bar.addWidget(self.btn_select_all)

        self.btn_deselect_all = QPushButton("Deseleccionar")
        self.btn_deselect_all.setFixedHeight(32)
        self.btn_deselect_all.clicked.connect(lambda: self.set_all_checked(False))
        top_bar.addWidget(self.btn_deselect_all)

        self.btn_clear_all = QPushButton("Limpiar Todo")
        self.btn_clear_all.setFixedHeight(32)
        self.set_button_variant(self.btn_clear_all, "danger")
        self.btn_clear_all.clicked.connect(self.clear_table)
        top_bar.addWidget(self.btn_clear_all)

        layout.addLayout(top_bar)

        # ===== Tabla =====
        self.table = QTableWidget()
        # MOTEC Style Columns + Cloud ID for custom API identifier:
        # Enable, ID, Channel, Cloud ID, Offset, Length, Mask, Type, Multiplier, Divisor, Adder, Min, Max, Unit, Byte Order, Live Value
        columns = ["Enable", "ID", "Channel", "Cloud ID", "Offset", "Length", "Mask", "Type", "Multiplier", "Divisor", "Adder", "Min", "Max", "Unit", "Byte Order", "Live Value"]
        self.table.setColumnCount(len(columns))
        self.table.setHorizontalHeaderLabels(columns)
        self.table.horizontalHeader().setSectionResizeMode(QHeaderView.Interactive)
        self.table.horizontalHeader().setStretchLastSection(True)
        self.table.setStyleSheet("""
            QTableWidget { background-color: #2d2d2d; color: white; gridline-color: #444; border: none; }
            QHeaderView::section { background-color: #3d3d3d; color: white; padding: 5px; border: none; }
        """)

        self.table.itemChanged.connect(self.on_table_item_changed)

        layout.addWidget(self.table)

        # Default: vista básica (reduce densidad)
        self.set_can_columns_mode(False)

    def on_table_item_changed(self, item):
        # Prevent recursion loop
        self.table.blockSignals(True)
        try:
            row = item.row()
            col = item.column()
            
            # Multiplier changed (Col.MULTIPLIER) -> Update Divisor
            if col == Col.MULTIPLIER:
                try:
                    text = item.text().replace(',', '.')
                    factor = float(text)
                    if factor != 0:
                        divisor = 1.0 / factor
                        # Format divisor nicely
                        if divisor.is_integer():
                            div_str = str(int(divisor))
                        else:
                            div_str = f"{divisor:.6g}"
                        
                        self.table.item(row, Col.DIVISOR).setText(div_str)
                    else:
                        self.table.item(row, Col.DIVISOR).setText("Inf")
                except ValueError:
                    pass

            # Divisor changed -> Update Multiplier
            elif col == Col.DIVISOR:
                try:
                    text = item.text().replace(',', '.')
                    divisor = float(text)
                    if divisor != 0:
                        factor = 1.0 / divisor
                        # Format factor nicely
                        if factor.is_integer():
                            fac_str = str(int(factor))
                        else:
                            fac_str = f"{factor:.6g}"
                        
                        self.table.item(row, Col.MULTIPLIER).setText(fac_str)
                    else:
                        self.table.item(row, Col.MULTIPLIER).setText("Inf")
                except ValueError:
                    pass
            
            # Byte Order changed -> Update UserRole to match text
            elif col == Col.BYTE_ORDER:
                text = item.text().strip().lower()
                if "big" in text:
                    item.setData(Qt.UserRole, "big_endian")
                else:
                    item.setData(Qt.UserRole, "little_endian")
                    
        finally:
            self.table.blockSignals(False)
    
    def set_all_checked(self, checked):
        state = Qt.Checked if checked else Qt.Unchecked
        for row in range(self.table.rowCount()):
            item = self.table.item(row, 0)
            if item:
                item.setCheckState(state)

    def set_can_columns_mode(self, advanced: bool):
        """Vista básica por defecto. En avanzado se muestran todas las columnas."""
        basic_cols = {Col.ENABLE, Col.CAN_ID, Col.CHANNEL, Col.CLOUD_ID, Col.LIVE_VALUE}

        for col in range(self.table.columnCount()):
            is_basic = col in basic_cols
            # En básico: ocultar todo lo que no sea básico.
            # En avanzado: mostrar todo.
            # En avanzado: mostrar todo.
            self.table.setColumnHidden(col, (not advanced) and (not is_basic))

    def clear_table(self):
        """Borra todas las filas de la tabla."""
        confirm = QMessageBox.question(
            self, "Confirmar", 
            "¿Estás seguro de que quieres borrar todos los sensores de la tabla?\n"
            "Esto no borrará la configuración del dispositivo hasta que hagas clic en 'Enviar Config'.",
            QMessageBox.Yes | QMessageBox.No
        )
        if confirm == QMessageBox.Yes:
            self.table.setRowCount(0)

    def filter_can_table(self, text: str):
        """Filtra filas por coincidencia parcial en ID / Channel / Cloud ID."""
        query = (text or "").strip().lower()

        if not query:
            for row in range(self.table.rowCount()):
                self.table.setRowHidden(row, False)
            return

        # Filtrar por: ID, Channel, Cloud ID
        for row in range(self.table.rowCount()):
            id_item = self.table.item(row, Col.CAN_ID)
            ch_item = self.table.item(row, Col.CHANNEL)
            cloud_item = self.table.item(row, Col.CLOUD_ID)

            hay = " ".join([
                (id_item.text() if id_item else ""),
                (ch_item.text() if ch_item else ""),
                (cloud_item.text() if cloud_item else ""),
            ]).lower()

            self.table.setRowHidden(row, query not in hay)

    def clear_console(self):
        self.console_output.clear()

    def toggle_console(self):
        """Muestra/oculta la consola inferior (drawer)."""
        if not hasattr(self, "console_widget"):
            return

        show = not self.console_widget.isVisible()
        self.console_widget.setVisible(show)

        # Ajustar tamaños del splitter para que se sienta como drawer
        if hasattr(self, "splitter") and self.splitter:
            if show:
                self.splitter.setSizes([650, 220])
            else:
                self.splitter.setSizes([1, 0])

    def log_console(self, text):
        self.console_output.append(text)
        # Auto scroll
        sb = self.console_output.verticalScrollBar()
        sb.setValue(sb.maximum())

    def setup_settings_tab(self):
        # Scroll para evitar minimum height gigante en pantallas 1080p
        outer = QVBoxLayout(self.tab_settings)
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

        self.input_cloud_interval = QLineEdit(str(DEFAULT_CLOUD_INTERVAL_MS))
        self.input_serial_interval = QLineEdit(str(DEFAULT_SERIAL_INTERVAL_MS))

        # Fuente principal de datos (Simplificado para el Usuario)
        self.combo_source = QComboBox()
        # Mapeo interno: Texto UI -> Valor Config (UPPERCASE for Firmware Compatibility)
        self.source_map = {
            "CAN (MCP2515)": "CAN_ONLY",
            "OBD (Bridge C3)": "OBD_BRIDGE",
            "OBD (Directo)": "OBD_DIRECT",
            "Híbrido (CAN + OBD)": "CAN_OBD"
        }
        self.combo_source.addItems(self.source_map.keys())
        self.combo_source.currentTextChanged.connect(self.on_source_changed_ui)
        
        device_layout.addRow("Modo de Operación:", self.combo_source)

        # IMU integrada
        self.chk_imu_enabled = QCheckBox("Habilitar IMU (MPU6050)")
        self.chk_imu_enabled.setChecked(False)

        device_layout.addRow("Device ID:", self.input_device_id)
        device_layout.addRow("Car ID:", self.input_car_id)
        device_layout.addRow("API URL:", self.input_api_url)
        device_layout.addRow("Cloud Upload Interval (ms):", self.input_cloud_interval)
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

    def setup_obd_tab(self):
        outer = QVBoxLayout(self.tab_obd)
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

        # Toggle OBD
        self.chk_obd_enabled = QCheckBox("Habilitar OBD (directo o bridge C3)")
        self.chk_obd_enabled.setChecked(True)
        layout.addRow("", self.chk_obd_enabled)

        # Modo
        self.combo_obd_mode = QComboBox()
        self.combo_obd_mode.addItems(["direct", "c3_bridge"])
        self.combo_obd_mode.setCurrentText("direct")
        layout.addRow("Modo OBD:", self.combo_obd_mode)

        # ELM WiFi params
        self.input_elm_ssid = QLineEdit("WiFi_OBDII")
        self.input_elm_pass = QLineEdit("")
        self.input_elm_pass.setEchoMode(QLineEdit.Password)
        self.input_elm_ip = QLineEdit("192.168.0.10")
        self.input_elm_port = QLineEdit("35000")
        layout.addRow("ELM SSID:", self.input_elm_ssid)
        layout.addRow("ELM Password:", self.input_elm_pass)
        layout.addRow("ELM IP:", self.input_elm_ip)
        layout.addRow("ELM Port:", self.input_elm_port)

        # PIDs y tiempos
        self.input_obd_pids = QLineEdit("0x0C,0x0D,0x04,0x05,0x10,0x0B,0x11,BAT,0x5E,0x2F")
        layout.addRow("PIDs (coma):", self.input_obd_pids)

        self.input_dtc_interval = QSpinBox()
        self.input_dtc_interval.setRange(1000, 3_600_000)
        self.input_dtc_interval.setSingleStep(1000)
        self.input_dtc_interval.setValue(300000)
        layout.addRow("DTC interval (ms):", self.input_dtc_interval)

        self.input_scan_interval = QSpinBox()
        self.input_scan_interval.setRange(1000, 3_600_000)
        self.input_scan_interval.setSingleStep(1000)
        self.input_scan_interval.setValue(600000)
        layout.addRow("Scan interval (ms):", self.input_scan_interval)

        # === Separador para UART Bridge ===
        uart_group = QGroupBox("UART Bridge (para modo c3_bridge)")
        uart_layout = QFormLayout()

        self.input_uart_rx = QSpinBox()
        self.input_uart_rx.setRange(0, 40)
        self.input_uart_rx.setValue(32)  # Pin RX del ESP32 Principal (recibe del TX del C3)
        uart_layout.addRow("UART RX Pin (GPIO):", self.input_uart_rx)

        self.input_uart_tx = QSpinBox()
        self.input_uart_tx.setRange(0, 40)
        self.input_uart_tx.setValue(33)  # Pin TX del ESP32 Principal (envía al RX del C3)
        uart_layout.addRow("UART TX Pin (GPIO):", self.input_uart_tx)

        self.input_uart_baud = QComboBox()
        self.input_uart_baud.addItems(["115200", "230400", "460800", "921600"])
        self.input_uart_baud.setCurrentText("460800")
        uart_layout.addRow("UART Baud:", self.input_uart_baud)

        uart_group.setLayout(uart_layout)
        layout.addRow(uart_group)

        # === Fuel Calculation (para OBD) ===
        fuel_group = QGroupBox("Cálculo de Combustible")
        fuel_layout = QFormLayout()

        self.combo_fuel_method = QComboBox()
        self.combo_fuel_method.addItems(["AUTO", "MAF", "MAP", "SPEED", "ECU"])
        self.combo_fuel_method.setCurrentText("AUTO")
        fuel_layout.addRow("Método:", self.combo_fuel_method)

        self.input_engine_disp = QDoubleSpinBox()
        self.input_engine_disp.setRange(0.5, 10.0)
        self.input_engine_disp.setValue(2.0)
        self.input_engine_disp.setSingleStep(0.1)
        self.input_engine_disp.setSuffix(" L")
        fuel_layout.addRow("Cilindrada:", self.input_engine_disp)

        self.input_ve_estimate = QDoubleSpinBox()
        self.input_ve_estimate.setRange(0.5, 1.5)
        self.input_ve_estimate.setValue(0.85)
        self.input_ve_estimate.setSingleStep(0.05)
        fuel_layout.addRow("VE Estimate:", self.input_ve_estimate)

        self.input_afr = QDoubleSpinBox()
        self.input_afr.setRange(10.0, 20.0)
        self.input_afr.setValue(14.7)
        self.input_afr.setSingleStep(0.1)
        fuel_layout.addRow("AFR Target:", self.input_afr)

        fuel_group.setLayout(fuel_layout)
        layout.addRow(fuel_group)

        # === Botón para ver OBD Payload Preview ===
        btn_obd_payload = QPushButton("📡 Ver OBD Payload Preview")
        btn_obd_payload.setStyleSheet("""
            QPushButton {
                background: qlineargradient(x1:0, y1:0, x2:1, y2:1, stop:0 #4CAF50, stop:1 #2E7D32);
                color: white;
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: bold;
            }
            QPushButton:hover { background: qlineargradient(x1:0, y1:0, x2:1, y2:1, stop:0 #66BB6A, stop:1 #388E3C); }
        """)
        btn_obd_payload.clicked.connect(self.show_obd_payload_preview)
        layout.addRow(btn_obd_payload)

    def setup_cloud_tab(self):
        # Scroll para evitar minimum height gigante en pantallas 1080p
        outer = QVBoxLayout(self.tab_cloud)
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
        self.btn_check_wifi.clicked.connect(self.check_wifi_status)
        self.set_button_variant(self.btn_check_wifi, "info")
        layout.addRow("", self.btn_check_wifi)

        # Initial State
        self.toggle_protocol_fields(self.combo_protocol.currentText())

    def check_wifi_status(self):
        if self.serial_manager.is_connected:
            self.serial_manager.write("CMD:CHECK_WIFI")
            self.log_console("Sent: CMD:CHECK_WIFI")
        else:
            QMessageBox.warning(self, "Error", "Not connected to serial port!")

    def toggle_protocol_fields(self, protocol):
        self.mqtt_group.setVisible(protocol == "mqtt")

    def on_source_changed_ui(self, text):
        """
        Maneja el cambio de modo en la UI con LÓGICA ESTRICTA (Prevention of Zombie States).
        La selección de 'Fuente de datos' es la VERDAD ABSOLUTA.
        """
        source_val = self.source_map.get(text, "CAN_ONLY")
        self.log_console(f"[UI] Modo cambiado a: {source_val}")
        
        # === LÓGICA DE ESTADOS ===
        
        if source_val == "CAN_ONLY":
            # Deshabilitar OBD
            self.chk_obd_enabled.setChecked(False)
            self.chk_obd_enabled.setEnabled(False) # Forzado por Source
            self.combo_obd_mode.setEnabled(False)
            # Podríamos deshabilitar toda la pestaña OBD, pero por ahora solo los controles clave
            
        elif source_val == "OBD_BRIDGE":
            # Forzar OBD Bridge
            self.chk_obd_enabled.setChecked(True)
            self.chk_obd_enabled.setEnabled(False) # Usuario no puede deshabilitarlo si eligió modo OBD
            
            self.combo_obd_mode.setCurrentText("c3_bridge")
            self.combo_obd_mode.setEnabled(False) # Forzado
            
            # Ajustar visibilidad (Idealmente) - Por ahora solo habilitamos lo necesario
            self.input_elm_ip.setEnabled(False) # No se usa en bridge (es UART)
            self.input_uart_rx.setEnabled(True)
            self.input_uart_tx.setEnabled(True)
            
        elif source_val == "OBD_DIRECT":
            # Forzar OBD Directo
            self.chk_obd_enabled.setChecked(True)
            self.chk_obd_enabled.setEnabled(False)
            
            self.combo_obd_mode.setCurrentText("direct")
            self.combo_obd_mode.setEnabled(False)
            
            # Ajustar visibilidad
            self.input_elm_ip.setEnabled(True)
            self.input_uart_rx.setEnabled(False) # UART del bridge no relevante (aunque Direct podría usar UART ELM, asumimos WiFi típico o ELM UART directo)
            
        elif source_val == "CAN_OBD":
            # Híbrido: Permitimos control manual limitado o forzamos activado
            self.chk_obd_enabled.setChecked(True)
            self.chk_obd_enabled.setEnabled(True) # Aquí dejamos elegir al usuario si quiere apagar la parte OBD momentáneamente
            self.combo_obd_mode.setEnabled(True)  # Puede elegir Bridge o Direct para la parte OBD


    def setup_live_tab(self):
        layout = QVBoxLayout(self.tab_live)
        
        # --- Dashboard Cards (V2 Style) ---
        cards_layout = QHBoxLayout()
        cards_layout.setSpacing(15)
        
        self.cards = {}
        
        # Helper to create cards
        def create_card(title, unit, color):
            card = QWidget()
            card.setStyleSheet(f"""
                QWidget {{
                    background-color: #252526;
                    border-radius: 10px;
                    border: 1px solid #333;
                }}
            """)
            card_layout = QVBoxLayout(card)
            
            lbl_title = QLabel(title)
            lbl_title.setStyleSheet("color: #aaa; font-size: 11px; font-weight: bold; border: none;")
            card_layout.addWidget(lbl_title)
            
            lbl_value = QLabel("--")
            lbl_value.setStyleSheet("color: white; font-size: 32px; font-weight: bold; border: none;")
            lbl_value.setAlignment(Qt.AlignRight)
            card_layout.addWidget(lbl_value)
            
            lbl_unit = QLabel(unit)
            lbl_unit.setStyleSheet(f"color: {color}; font-size: 12px; border: none;")
            lbl_unit.setAlignment(Qt.AlignRight)
            card_layout.addWidget(lbl_unit)
            
            cards_layout.addWidget(card)
            return lbl_value
            
        self.cards["rpm"] = create_card("RPM", "rpm", "#007acc")
        self.cards["speed"] = create_card("VELOCIDAD", "km/h", "#4ec9b0")
        self.cards["temp"] = create_card("TEMP MOTOR", "°C", "#ce9178")
        self.cards["batt"] = create_card("BATERÍA", "V", "#dcdcaa")
        self.cards["fuel"] = create_card("COMBUSTIBLE", "%", "#9cdcfe")
        
        layout.addLayout(cards_layout)
        
        # --- Live Table (Existing) ---
        layout.addSpacing(20)
        lbl_table = QLabel("Detalle de Sensores")
        lbl_table.setStyleSheet("font-weight: bold; color: #ddd;")
        layout.addWidget(lbl_table)
        
        self.live_table = QTableWidget()
        self.live_table.setColumnCount(2)
        self.live_table.setHorizontalHeaderLabels(["Sensor Name", "Live Value"])
        self.live_table.horizontalHeader().setSectionResizeMode(QHeaderView.Stretch)
        self.live_table.setStyleSheet("""
            QTableWidget { background-color: #2d2d2d; color: white; gridline-color: #444; border: none; font-size: 14px; }
            QHeaderView::section { background-color: #3d3d3d; color: white; padding: 5px; border: none; font-weight: bold; }
        """)
        
        layout.addWidget(self.live_table)
        
        # Mapping from Sensor Name to Row Index for fast updates
        self.live_row_map = {}

    def get_config_data(self):
        # Gather Messages based on selected signals from the TABLE (WYSIWYG)
        selected_map = {} # frame_id -> message_object
        
        for row in range(self.table.rowCount()):
            checkbox_item = self.table.item(row, 0)
            if checkbox_item.checkState() == Qt.Checked:
                try:
                    # Get CAN ID from column 1 (works even if UserRole is None)
                    id_item = self.table.item(row, 1)
                    if id_item is None:
                        print(f"Row {row}: Column 1 (ID) is None, skipping")
                        continue
                    
                    id_text = id_item.text().strip()
                    
                    # Validate it looks like a CAN ID
                    if not id_text:
                        print(f"Row {row}: Empty CAN ID, skipping")
                        continue
                    
                    # Try to parse as hex or decimal
                    if id_text.lower().startswith("0x"):
                        frame_id = int(id_text, 16)
                    elif id_text.isdigit():
                        frame_id = int(id_text)
                    else:
                        # Maybe it's a hex without 0x prefix
                        try:
                            frame_id = int(id_text, 16)
                        except ValueError:
                            print(f"Row {row}: Invalid CAN ID '{id_text}', skipping")
                            continue
                    
                    # NEW COLUMNS MAP (with Cloud ID at col 3):
                    # 2: Channel, 3: Cloud ID, 4: Offset, 5: Len, 6: Mask, 7: Type, 8: Mult, 9: Div, 10: Adder, 11: Min, 12: Max, 13: Unit, 14: ByteOrder, 15: Live
                    
                    name = self.table.item(row, 2).text()
                    cloud_id = self.table.item(row, 3).text().strip()  # Cloud ID for API
                    if not cloud_id:
                        cloud_id = name  # Fallback to name if empty
                    
                    offset_byte = int(self.table.item(row, 4).text())
                    len_byte = int(self.table.item(row, 5).text())
                    # mask = self.table.item(row, 6).text() # Not used for logic yet
                    
                    is_signed = (self.table.item(row, 7).text() == "Signed")
                    
                    factor_str = self.table.item(row, 8).text().replace(',', '.')
                    adder_str = self.table.item(row, 10).text().replace(',', '.')
                    
                    factor = float(factor_str) if factor_str and factor_str != "-" else 1.0
                    offset = float(adder_str) if adder_str and adder_str != "-" else 0.0
                    
                    min_val = float(self.table.item(row, 11).text().replace(',', '.'))
                    max_val = float(self.table.item(row, 12).text().replace(',', '.'))
                    
                    # Byte Order from column 14
                    byte_order_item = self.table.item(row, 14)
                    byte_order = byte_order_item.data(Qt.UserRole) if byte_order_item else "big_endian"
                    if not byte_order:  # Fallback if UserRole not set
                        byte_order = "big_endian" if "Big" in byte_order_item.text() else "little_endian"
                    
                    # Convert Back to Firmware Format (Bits)
                    # Start Bit = Offset Byte * 8.
                    start_bit = offset_byte * 8
                    # Length Bit = Len Byte * 8 (Approximation for byte aligned)
                    length_bit = len_byte * 8
                    
                    # Construct Signal with Cloud ID
                    new_signal = GenericSignal(
                        name=name,
                        start=start_bit,
                        length=length_bit,
                        is_signed=is_signed,
                        scale=factor,
                        offset=offset,
                        minimum=min_val,
                        maximum=max_val,
                        unit="",
                        byte_order=byte_order
                    )
                    # Store cloud_id as an additional attribute
                    new_signal.cloud_id = cloud_id
                    
                    if frame_id not in selected_map:
                        selected_map[frame_id] = GenericMessage(frame_id, name)
                    
                    selected_map[frame_id].signals.append(new_signal)
                    
                except ValueError as e:
                    print(f"Error parsing row {row}: {e}")
                    continue

        # Gather Settings (schema unificado)
        settings = {
            "version": "3.0",
            
            "device": {
                "id": self.input_device_id.text(),
                "car_id": self.input_car_id.text(),
                # Translate UI text to config value
                "source": self.source_map.get(self.combo_source.currentText(), "can_only")
            },
            
            "wifi": {
                "ssid": self.input_ssid.text(),
                "password": self.input_pass.text()  # FIXED: key "pass" -> "password"
            },
            
            "cloud": {
                "protocol": self.combo_protocol.currentText(),
                "interval_ms": int(self.input_cloud_interval.text()),
                "debug_mode": self.chk_debug_mode.isChecked(),
                "mqtt": {
                    "server": self.input_mqtt_server.text(),
                    "port": int(self.input_mqtt_port.text() or 1883),
                    "user": self.input_mqtt_user.text(),
                    "password": self.input_mqtt_pass.text(), # FIXED location
                    "topic": self.input_mqtt_topic.text()
                },
                "http": {
                    "url": self.input_api_url.text()
                }
            },
            
            "serial": {
                "interval_ms": int(self.input_serial_interval.text())
            },
            
            "can": {
                "enabled": True, # Always true if configuring, or based on source selection logic if needed
                "cs_pin": self.input_cs_pin.value(),
                "int_pin": self.input_int_pin.value(),
                "baud_kbps": int(self.combo_baud.currentText()),
                "crystal_mhz": int(self.combo_crystal.currentText())
            },
            
            "obd": {
                "enabled": self.chk_obd_enabled.isChecked(),
                "mode": "bridge" if self.combo_obd_mode.currentText() == "c3_bridge" else "direct",
                "pids_enabled": self.input_obd_pids.text(), # pass raw string, firmware parses it
                "poll_interval_ms": 200, # default/fixed? or add UI field if missing
                "elm_wifi": {
                    "ssid": self.input_elm_ssid.text(),
                    "password": self.input_elm_pass.text(),
                    "ip": self.input_elm_ip.text(),
                    "port": int(self.input_elm_port.text() or 35000)
                },
                "uart": {
                    "rx_pin": self.input_uart_rx.value(),
                    "tx_pin": self.input_uart_tx.value(),
                    "baud": int(self.input_uart_baud.currentText())
                }
            },
            
            "gps": {
                "enabled": self.chk_gps_enabled.isChecked(),
                "rx_pin": self.input_gps_rx_pin.value(),
                "tx_pin": self.input_gps_tx_pin.value(),
                "baud": 9600
            },
            
            "imu": {
                "enabled": self.chk_imu_enabled.isChecked(),
                "sda_pin": 21, # Default I2C SDA
                "scl_pin": 22  # Default I2C SCL
            },

            "fuel": {
                "method": self.combo_fuel_method.currentText(),
                "displacement_l": self.input_engine_disp.value(),
                "volumetric_efficiency": self.input_ve_estimate.value(),
                "air_fuel_ratio": self.input_afr.value()
            }
        }

        return JSONGenerator.generate_config(list(selected_map.values()), settings=settings)


    def apply_dark_theme(self):
        app = QApplication.instance()
        app.setStyle("Fusion")
        
        palette = QPalette()
        palette.setColor(QPalette.Window, QColor(30, 30, 30))
        palette.setColor(QPalette.WindowText, Qt.white)
        palette.setColor(QPalette.Base, QColor(25, 25, 25))
        palette.setColor(QPalette.AlternateBase, QColor(35, 35, 35))
        palette.setColor(QPalette.ToolTipBase, Qt.white)
        palette.setColor(QPalette.ToolTipText, Qt.white)
        palette.setColor(QPalette.Text, Qt.white)
        palette.setColor(QPalette.Button, QColor(45, 45, 45))
        palette.setColor(QPalette.ButtonText, Qt.white)
        palette.setColor(QPalette.BrightText, Qt.red)
        palette.setColor(QPalette.Link, QColor(42, 130, 218))
        palette.setColor(QPalette.Highlight, QColor(42, 130, 218))
        palette.setColor(QPalette.HighlightedText, Qt.black)
        
        app.setPalette(palette)

    def set_button_variant(self, btn: QPushButton, variant: str):
        """Aplica una variante de estilo vía property para mantener consistencia visual."""
        if btn is None:
            return
        btn.setProperty("variant", variant)
        # Re-aplicar QSS
        btn.style().unpolish(btn)
        btn.style().polish(btn)
        btn.update()

    def apply_global_styles(self):
        """Estilo global (QSS) manteniendo la paleta original pero con jerarquía consistente."""
        app = QApplication.instance()
        app.setStyleSheet("""
            QWidget {
                font-family: Segoe UI;
                font-size: 13px;
            }

            /* Inputs */
            QLineEdit, QComboBox, QSpinBox, QDoubleSpinBox {
                background-color: #1e1e1e;
                border: 1px solid #444;
                border-radius: 6px;
                padding: 6px 8px;
                color: #eee;
            }
            QLineEdit:focus, QComboBox:focus, QSpinBox:focus, QDoubleSpinBox:focus {
                border: 1px solid #00ffcc;
            }

            /* GroupBox */
            QGroupBox {
                border: 1px solid #444;
                border-radius: 10px;
                margin-top: 12px;
                padding: 10px;
            }
            QGroupBox::title {
                subcontrol-origin: margin;
                left: 10px;
                padding: 0 6px;
                color: #ddd;
            }

            /* Base button */
            QPushButton {
                background-color: #2d2d2d;
                color: #eee;
                border: 1px solid #444;
                border-radius: 6px;
                padding: 6px 10px;
            }
            QPushButton:hover { border-color: #00ffcc; }
            QPushButton:pressed { background-color: #333; }
            QPushButton:disabled {
                background-color: #242424;
                border-color: #333;
                color: #777;
            }

            /* Variantes (misma paleta, ajuste de brillo/saturación) */
            QPushButton[variant="primary"] { background-color: #00aa88; border-color: #00aa88; color: #ffffff; }
            QPushButton[variant="primary"]:hover { background-color: #00c9a0; border-color: #00c9a0; }

            QPushButton[variant="info"] { background-color: #2980b9; border-color: #2980b9; color: #ffffff; }
            QPushButton[variant="info"]:hover { background-color: #3498db; border-color: #3498db; }

            QPushButton[variant="success"] { background-color: #27ae60; border-color: #27ae60; color: #ffffff; }
            QPushButton[variant="success"]:hover { background-color: #2ecc71; border-color: #2ecc71; }

            QPushButton[variant="warning"] { background-color: #e67e22; border-color: #e67e22; color: #ffffff; }
            QPushButton[variant="warning"]:hover { background-color: #f39c12; border-color: #f39c12; }

            QPushButton[variant="accent"] { background-color: #8e44ad; border-color: #8e44ad; color: #ffffff; }
            QPushButton[variant="accent"]:hover { background-color: #9b59b6; border-color: #9b59b6; }

            QPushButton[variant="danger"] { background-color: #c0392b; border-color: #c0392b; color: #ffffff; }
            QPushButton[variant="danger"]:hover { background-color: #e74c3c; border-color: #e74c3c; }

            QPushButton[variant="secondary"] { background-color: #3a3a3a; border-color: #4a4a4a; color: #ffffff; }
            QPushButton[variant="secondary"]:hover { border-color: #00ffcc; }

            QPushButton[variant="ghost"] { background-color: transparent; border-color: #444; color: #ddd; }
            QPushButton[variant="ghost"]:hover { background-color: #2a2a2a; border-color: #00ffcc; }

            /* Tabs */
            QTabWidget::pane { border: 1px solid #444; }
            QTabBar::tab {
                background: #333;
                color: #aaa;
                padding: 10px 14px;
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
                margin-right: 4px;
            }
            QTabBar::tab:selected {
                background: #3d3d3d;
                color: white;
                border-bottom: 2px solid #00ffcc;
            }
        """)

    def load_dbc(self):
        file_path, _ = QFileDialog.getOpenFileName(self, "Open DBC File", "", "DBC Files (*.dbc);;All Files (*)")
        if file_path:
            success, message = self.parser.load_file(file_path)
            if success:
                self.status_label.setText(f"Loaded: {os.path.basename(file_path)}")
                self.populate_table()
            else:
                self.status_label.setText(f"Error: {message}")

    def add_signal_row(self, msg, signal, cloud_id=None):
        print(f"[DEBUG] Adding row for signal {signal.name}")
        self.table.blockSignals(True) # optimization: prevent triggering itemChanged 10x per row
        row = self.table.rowCount()
        self.table.insertRow(row)
        
        # 0: Enable
        check_item = QTableWidgetItem()
        check_item.setFlags(Qt.ItemIsUserCheckable | Qt.ItemIsEnabled)
        check_item.setCheckState(Qt.Checked)
        self.table.setItem(row, 0, check_item)

        # 1: ID (CAN ID)
        id_item = QTableWidgetItem(f"0x{msg.frame_id:X}")
        self.table.setItem(row, 1, id_item)
        
        # 2: Channel (Signal Name)
        sig_item = QTableWidgetItem(signal.name)
        sig_item.setData(Qt.UserRole, msg)
        sig_item.setData(Qt.UserRole + 1, signal)
        self.table.setItem(row, 2, sig_item)
        
        # 3: Cloud ID (Custom ID for cloud API - default to signal name)
        cloud_id_value = cloud_id if cloud_id else signal.name
        cloud_id_item = QTableWidgetItem(cloud_id_value)
        cloud_id_item.setToolTip("ID personalizado para usar en el JSON de la nube")
        self.table.setItem(row, 3, cloud_id_item)
        
        # CALCULATION FOR MOTEC FORMAT
        # Offset (Bytes) = Start // 8
        offset_byte = signal.start // 8
        
        # Length (Bytes) = Ceil(Length / 8)
        len_byte = (signal.length + 7) // 8
        
        # Mask: (1 << length) - 1. Display as Hex.
        mask_val = (1 << signal.length) - 1
        mask_str = f"{mask_val:X}"
        
        # 4: Offset
        self.table.setItem(row, 4, QTableWidgetItem(str(offset_byte)))
        
        # 5: Length
        self.table.setItem(row, 5, QTableWidgetItem(str(len_byte)))
        
        # 6: Mask
        self.table.setItem(row, 6, QTableWidgetItem(mask_str))
        
        # 7: Type
        self.table.setItem(row, 7, QTableWidgetItem("Signed" if signal.is_signed else "Unsigned"))
        
        # 8: Multiplier (Factor)
        self.table.setItem(row, 8, QTableWidgetItem(str(signal.scale)))
        
        # 9: Divisor
        try:
            factor = float(signal.scale)
            if factor != 0:
                divisor = 1.0 / factor
                if divisor.is_integer():
                    div_str = str(int(divisor))
                else:
                    div_str = f"{divisor:.6g}"
            else:
                div_str = "Inf"
        except:
            div_str = "-"
        self.table.setItem(row, 9, QTableWidgetItem(div_str))

        # 10: Adder (Offset)
        self.table.setItem(row, 10, QTableWidgetItem(str(signal.offset)))
        
        # 11: Min
        self.table.setItem(row, 11, QTableWidgetItem(str(signal.minimum)))
        
        # 12: Max
        self.table.setItem(row, 12, QTableWidgetItem(str(signal.maximum)))
        
        # 13: Unit
        self.table.setItem(row, 13, QTableWidgetItem(str(signal.unit) if signal.unit else ""))
        
        # 14: Byte Order (Big Endian = Motec/Motorola, Little Endian = Intel)
        byte_order_str = "Big Endian" if signal.byte_order == "big_endian" else "Little Endian"
        byte_order_item = QTableWidgetItem(byte_order_str)
        byte_order_item.setData(Qt.UserRole, signal.byte_order)  # Store raw value
        self.table.setItem(row, 14, byte_order_item)
        
        # 15: Live Value
        val_item = QTableWidgetItem("-")
        val_item.setTextAlignment(Qt.AlignCenter)
        self.table.setItem(row, 15, val_item)
        
        self.table.blockSignals(False)

    def populate_table(self):
        messages = self.parser.get_messages()
        self.table.setRowCount(0) # Clear table
        
        for msg in messages:
            for signal in msg.signals:
                self.add_signal_row(msg, signal)

    def import_xml_config(self):
        file_path, _ = QFileDialog.getOpenFileName(self, "Import XML Config", "", "XML Files (*.xml);;All Files (*)")
        if not file_path: return
        
        success, result = XmlLoader.parse_file(file_path)
        if success:
            messages = result # List of dicts: {name, id, signals: []}
            self.table.setRowCount(0)
            
            # Convert to GenericMessage structure
            for m_dict in messages:
                msg = GenericMessage(m_dict["id"], m_dict["name"])
                for s_dict in m_dict["signals"]:
                    signal = GenericSignal(
                        name=s_dict["name"],
                        start=s_dict["start_bit"],
                        length=s_dict["length"],
                        is_signed=s_dict["signed"],
                        scale=s_dict["scale"],
                        offset=s_dict["offset"],
                        minimum=0,
                        maximum=10000,
                        unit=s_dict["unit"],
                        byte_order=s_dict["byte_order"]
                    )
                    self.add_signal_row(msg, signal)
            
            self.status_label.setText(f"Imported XML: {os.path.basename(file_path)}")
        else:
            QMessageBox.critical(self, "Import Error", result)

    def import_json_config(self):
        file_path, _ = QFileDialog.getOpenFileName(self, "Import JSON Config", "", "JSON Files (*.json)")
        if not file_path: return
        
        try:
            with open(file_path, 'r') as f:
                data = json.load(f)
            
            self.load_configuration(data)
            self.status_label.setText(f"Imported: {os.path.basename(file_path)}")
        except Exception as e:
            QMessageBox.critical(self, "Import Error", str(e))

    def load_configuration(self, data):
        """Carga la configuración en la UI (Strict Unified Schema V3)."""
        print(f"[DEBUG] Raw Config Data Keys: {list(data.keys())}")
        
        # --- 1. DEVICE ---
        if "device" in data:
            d = data["device"]
            self.input_device_id.setText(d.get("id", ""))
            self.input_car_id.setText(d.get("car_id", ""))
            # Reverse map source value to UI text
            src_val = d.get("source", "CAN_ONLY")
            # Create a reverse map: value -> key
            reverse_map = {v: k for k, v in self.source_map.items()}
            # Default to CAN (MCP2515) if not found
            ui_text = reverse_map.get(src_val, "CAN (MCP2515)") 
            self.combo_source.setCurrentText(ui_text)
        
        # --- 2. WIFI ---
        if "wifi" in data:
            w = data["wifi"]
            self.input_ssid.setText(w.get("ssid", ""))
            self.input_pass.setText(w.get("password", ""))

        # --- 3. CLOUD ---
        if "cloud" in data:
            c = data["cloud"]
            self.combo_protocol.setCurrentText(c.get("protocol", "mqtt"))
            self.input_cloud_interval.setText(str(c.get("interval_ms", 1000)))
            self.chk_debug_mode.setChecked(c.get("debug_mode", False))
            
            if "mqtt" in c:
                m = c["mqtt"]
                self.input_mqtt_server.setText(m.get("server", ""))
                self.input_mqtt_port.setText(str(m.get("port", 1883)))
                self.input_mqtt_user.setText(m.get("user", ""))
                self.input_mqtt_pass.setText(m.get("password", ""))
                self.input_mqtt_topic.setText(m.get("topic", ""))
                
            if "http" in c:
                self.input_api_url.setText(c["http"].get("url", ""))

        # --- 4. SERIAL ---
        if "serial" in data:
            self.input_serial_interval.setText(str(data["serial"].get("interval_ms", 500)))

        # --- 5. CAN ---
        if "can" in data:
            cn = data["can"]
            self.input_cs_pin.setValue(cn.get("cs_pin", 5))
            self.input_int_pin.setValue(cn.get("int_pin", 4))
            self.combo_baud.setCurrentText(str(cn.get("baud_kbps", 500)))
            self.combo_crystal.setCurrentText(str(cn.get("crystal_mhz", 8)))

        # --- 6. OBD ---
        if "obd" in data:
            obd = data["obd"]
            self.chk_obd_enabled.setChecked(obd.get("enabled", False))
            # CRÍTICO: El firmware usa "bridge", el UI usa "c3_bridge"
            mode_from_fw = obd.get("mode", "direct")
            ui_mode = "c3_bridge" if mode_from_fw == "bridge" else "direct"
            self.combo_obd_mode.setCurrentText(ui_mode)
            self.input_obd_pids.setText(obd.get("pids_enabled", ""))
            
            if "elm_wifi" in obd:
                elm = obd["elm_wifi"]
                self.input_elm_ssid.setText(elm.get("ssid", ""))
                self.input_elm_pass.setText(elm.get("password", ""))
                self.input_elm_ip.setText(elm.get("ip", ""))
                self.input_elm_port.setText(str(elm.get("port", 35000)))
            
            if "uart" in obd:
                u = obd["uart"]
                self.input_uart_rx.setValue(u.get("rx_pin", 32))
                self.input_uart_tx.setValue(u.get("tx_pin", 33))
                self.input_uart_baud.setCurrentText(str(u.get("baud", 460800)))

        # --- 7. GPS & IMU ---
        if "gps" in data:
            self.chk_gps_enabled.setChecked(data["gps"].get("enabled", False))
            self.input_gps_rx_pin.setValue(data["gps"].get("rx_pin", 16))
            self.input_gps_tx_pin.setValue(data["gps"].get("tx_pin", 17))
            
        if "imu" in data:
            self.chk_imu_enabled.setChecked(data["imu"].get("enabled", False))

        # --- 8. FUEL ---
        if "fuel" in data:
            f = data["fuel"]
            self.combo_fuel_method.setCurrentText(f.get("method", "auto"))
            self.input_engine_disp.setValue(f.get("displacement_l", 2.0))
            self.input_ve_estimate.setValue(f.get("volumetric_efficiency", 0.85))
            self.input_afr.setValue(f.get("air_fuel_ratio", 14.7))

        # --- 9. SENSORS (Unified) ---
        if "sensors" in data and isinstance(data["sensors"], list):
            self.log_console(f"[DEBUG] Loading {len(data['sensors'])} sensors...")
            self.table.setRowCount(0)
            
            for idx, s in enumerate(data["sensors"]):
                try:
                    # Mandatory
                    can_id = s.get("can_id", 0)
                    name = s.get("name", f"Sensor_{idx}")
                    
                    msg = GenericMessage(can_id)
                    
                    # --- Determine Start Bit ---
                    # Priority: start_bit > start_byte * 8 > offset * 8
                    start_bit = 0
                    if "start_bit" in s:
                        start_bit = s["start_bit"]
                    elif "start_byte" in s:
                        start_bit = s["start_byte"] * 8
                    elif "offset" in s: # Motec byte offset
                        if isinstance(s["offset"], int):
                             start_bit = s["offset"] * 8
                    
                    # --- Determine Length (Bits) ---
                    # Priority: length > length_bytes * 8
                    length_bits = 16
                    if "length" in s:
                        length_bits = s["length"]
                    elif "length_bytes" in s:
                        length_bits = s["length_bytes"] * 8

                    # --- Determine Value Offset (Adder) ---
                    # IMPORTANT: Firmware exports 'offset' as float adder.
                    # Motec generator uses 'offset_value' as float adder.
                    # We handle both.
                    adder = s.get("offset_value", 0.0)
                    if adder == 0.0 and "offset" in s:
                        # If offset is float or large, treat as adder
                        val = s["offset"]
                        if isinstance(val, float) or val >= 64: 
                            adder = val
                        # If offset is small int, it might be byte offset, ignore as adder unless confirmed
                    
                    signal = GenericSignal(
                        name=name,
                        start=start_bit,
                        length=length_bits,
                        is_signed=s.get("signed", False),
                        scale=s.get("multiplier", 1.0),
                        offset=float(adder),
                        minimum=s.get("min", 0),
                        maximum=s.get("max", 100),
                        unit=s.get("unit", ""),
                        byte_order=s.get("byte_order", "big_endian")
                    )
                    
                    cloud_id = s.get("cloud_id", name)
                    self.add_signal_row(msg, signal, cloud_id=cloud_id)
                    
                except Exception as e:
                    print(f"Error parse sensor {idx}: {e}")
        else:
             print("[DEBUG] No 'sensors' list in config data")

        # Force clear filter to ensure rows are visible
        self.input_can_search.clear()
        self.filter_can_table("")

        self.log_console("[CONFIG] Configuration Loaded (V3 Strict)")


    
    def download_config(self):
        if not self.serial_manager.is_connected:
            return

        # Stop UI updater while downloading
        self.timer.stop()
        self.status_label.setText("Starting Download...")
        
        # Step 1: Get Config
        self.worker = SerialWorker(self.serial_manager, "GET_CONFIG", expected_prefix="CONFIG:")
        self.worker.progress.connect(lambda s: self.status_label.setText(s))
        self.worker.finished.connect(self.on_config_download_success)
        self.worker.error.connect(self.on_download_error)
        self.worker.start()

    def on_config_download_success(self, data):
        self.temp_config_data = data # Store for merge
        self.status_label.setText("Config Downloaded. Fetching Sensors...")
        
        # Step 2: Get Sensors
        self.worker = SerialWorker(self.serial_manager, "GET_SENSORS", expected_prefix="SENSORS:")
        self.worker.progress.connect(lambda s: self.status_label.setText(s))
        self.worker.finished.connect(self.on_sensors_download_success)
        self.worker.error.connect(self.on_download_error)
        self.worker.start()

    def on_sensors_download_success(self, data):
        # Merge sensors into main config
        final_config = self.temp_config_data
        if "sensors" in data:
            final_config["sensors"] = data["sensors"]
            
        self.load_configuration(final_config)
        self.status_label.setText("Configuration & Sensors Downloaded Successfully")
        self.log_console("[INFO] Full configuration downloaded.")
        
        # Restart internal timer
        self.timer.start(UI_REFRESH_INTERVAL_MS)

    def on_download_error(self, err_msg):
        self.status_label.setText(f"Error: {err_msg}")
        self.log_console(f"[ERROR] {err_msg}")
        self.timer.start(UI_REFRESH_INTERVAL_MS)

    def check_wifi_status(self):
        if self.serial_manager.is_connected:
            self.serial_manager.write("GET_STATUS")
            self.log_console("Sent: GET_STATUS")
        else:
            QMessageBox.warning(self, "Error", "Not connected to serial port!")

    def add_manual_sensor_dialog(self):
        dialog = QDialog(self)
        dialog.setWindowTitle("Add Manual Sensor (Motec)")
        form = QFormLayout(dialog)
        
        inp_name = QLineEdit()
        inp_can_id = QLineEdit() # Hex
        
        # Motec Fields
        inp_offset = QSpinBox() # Byte Offset
        inp_offset.setRange(0, 63)
        
        inp_len_byte = QSpinBox() # Byte Length
        inp_len_byte.setRange(1, 8)
        inp_len_byte.setValue(2) # Default 2 bytes (16 bits)
        
        inp_mask = QLineEdit("FFFF") # Hex Mask
        
        inp_signed = QCheckBox("Signed")
        
        inp_multiplier = QDoubleSpinBox()
        inp_multiplier.setValue(1.0)
        inp_multiplier.setRange(-1000000, 1000000)
        
        inp_adder = QDoubleSpinBox()
        inp_adder.setRange(-1000000, 1000000)
        
        inp_min = QDoubleSpinBox()
        inp_min.setRange(-1000000, 1000000)
        inp_max = QDoubleSpinBox()
        inp_max.setRange(-1000000, 1000000)
        inp_max.setValue(100)
        
        # Byte Order ComboBox (Big Endian = Motec default)
        inp_byte_order = QComboBox()
        inp_byte_order.addItems(["Big Endian (Motec)", "Little Endian (Intel)"])
        inp_byte_order.setCurrentIndex(0)  # Default: Big Endian for Motec
        
        form.addRow("Channel Name:", inp_name)
        form.addRow("CAN ID (Hex):", inp_can_id)
        form.addRow("Offset (Bytes):", inp_offset)
        form.addRow("Length (Bytes):", inp_len_byte)
        form.addRow("Mask (Hex):", inp_mask)
        form.addRow("Signed:", inp_signed)
        form.addRow("Multiplier:", inp_multiplier)
        form.addRow("Adder:", inp_adder)
        form.addRow("Min (UI only):", inp_min)
        form.addRow("Max (UI only):", inp_max)
        form.addRow("Byte Order:", inp_byte_order)
        
        buttons = QDialogButtonBox(QDialogButtonBox.Ok | QDialogButtonBox.Cancel)
        buttons.accepted.connect(dialog.accept)
        buttons.rejected.connect(dialog.reject)
        form.addRow(buttons)
        
        if dialog.exec() == QDialog.Accepted:
            try:
                can_id_str = inp_can_id.text()
                # Auto detect if 0x prefix
                if can_id_str.lower().startswith("0x"):
                    frame_id = int(can_id_str, 16)
                else:
                    frame_id = int(can_id_str, 16)
                
                name = inp_name.text()
                if not name: name = f"Sensor_{frame_id:X}"
                
                offset_byte = inp_offset.value()
                len_byte = inp_len_byte.value()
                
                # Convert back to internal bits for GenericSignal
                start_bit = offset_byte * 8
                length_bit = len_byte * 8
                
                # Get byte order from combo
                byte_order = "big_endian" if inp_byte_order.currentIndex() == 0 else "little_endian"
                
                msg = GenericMessage(frame_id)
                signal = GenericSignal(
                    name=name,
                    start=start_bit,
                    length=length_bit,
                    is_signed=inp_signed.isChecked(),
                    scale=inp_multiplier.value(),
                    offset=inp_adder.value(),
                    minimum=inp_min.value(),
                    maximum=inp_max.value(),
                    unit="",
                    byte_order=byte_order
                )
                self.add_signal_row(msg, signal)
            except ValueError:
                QMessageBox.warning(self, "Error", "Invalid Input (Check CAN ID)")

    def export_json(self):
        config_data = self.get_config_data()
        
        if not config_data["sensors"]:
            confirm = QMessageBox.question(
                self, "Confirmar Exportación", 
                "La lista de sensores está vacía.\n¿Desea exportar el JSON de todas formas?",
                QMessageBox.Yes | QMessageBox.No
            )
            if confirm != QMessageBox.Yes:
                return

        file_path, _ = QFileDialog.getSaveFileName(self, "Save JSON Config", "config.json", "JSON Files (*.json)")
        if file_path:
            try:
                with open(file_path, 'w') as f:
                    json.dump(config_data, f, indent=2)
                self.status_label.setText(f"Exported to: {os.path.basename(file_path)}")
            except Exception as e:
                self.status_label.setText(f"Export Error: {str(e)}")

    def refresh_ports(self):
        ports = self.serial_manager.list_ports()
        self.combo_ports.clear()
        self.combo_ports.addItems(ports)

    def toggle_connection(self):
        if self.btn_connect.text() == "Conectar":
            port = self.combo_ports.currentText()
            if not port:
                return
            success, msg = self.serial_manager.connect(port)
            if success:
                self.btn_connect.setText("Desconectar")
                self.set_button_variant(self.btn_connect, "danger")
                self.btn_upload.setEnabled(True)
                self.btn_download.setEnabled(True)
                self.btn_memory.setEnabled(True)
                self.btn_debug.setEnabled(True)
                self.status_label.setText(msg)
                
                # Enable Monitor Button
                self.btn_monitor.setEnabled(True)

                # Dar tiempo al ESP32 para estabilizarse después de la conexión
                import time
                self.log_console("[INFO] Esperando estabilización del dispositivo...")
                QApplication.processEvents()
                time.sleep(0.5)  # Delay adicional para asegurar estabilidad

                # Auto Download Config
                self.log_console("[INFO] Solicitando configuración del dispositivo...")
                self.download_config()
            else:
                QMessageBox.critical(self, "Error de conexión", msg)
        else:
            success, msg = self.serial_manager.disconnect()
            self.btn_connect.setText("Conectar")
            self.set_button_variant(self.btn_connect, "secondary")
            self.btn_upload.setEnabled(False)
            self.btn_download.setEnabled(False)
            self.btn_memory.setEnabled(False)  # Deshabilitar botón de memoria
            self.btn_debug.setEnabled(False)
            self.btn_monitor.setEnabled(False)
            self.btn_monitor.setText("▶ Monitor")
            self.monitoring = False
            self.status_label.setText(msg)

    def upload_config(self):
        config_data = self.get_config_data()
        
        # Permitir enviar lista vacía (útil para limpiar o modo solo OBD)
        if not config_data["sensors"]:
            confirm = QMessageBox.question(
                self, "Confirmar Carga", 
                "La lista de sensores CAN está VACÍA.\n"
                "Esto borrará todos los sensores CAN del dispositivo.\n"
                "¿Desea continuar?",
                QMessageBox.Yes | QMessageBox.No
            )
            if confirm != QMessageBox.Yes:
                return

        success, msg = self.serial_manager.send_config(config_data)
        if success:
            QMessageBox.information(self, "Success", msg)
        else:
            QMessageBox.critical(self, "Upload Error", msg)

    def send_debug_command(self):
        """Envía el comando de diagnóstico al ESP32."""
        if not self.serial_manager.is_connected:
            return
            
        self.log_console("[INFO] Solicitando diagnóstico del sistema...")
        self.log_console("[INFO] Enviando GET_DIAG y GET_STATUS")
        self.serial_manager.write("GET_DIAG")
        self.serial_manager.write("GET_STATUS")
        self.status_label.setText("Diagnóstico solicitado - revisa consola")

    def show_obd_payload_preview(self):
        """Muestra un preview del payload OBD que se envía al cloud MQTT/HTTP."""
        from datetime import datetime
        from PySide6.QtWidgets import QDialog, QTextEdit
        
        # PIDs OBD2 soportados por el ESP32-C3
        OBD_PIDS_INFO = [
            ("0x0C", "RPM", "rpm", "Revoluciones del motor", "RPM"),
            ("BAT", "BATT_V", "batt_v", "Voltaje de batería", "V"),
            ("0x05", "COOLANT", "coolant_temp", "Temperatura refrigerante", "°C"),
            ("0x04", "LOAD", "load", "Carga del motor", "%"),
            ("0x0F", "IAT", "engine.intake_temp", "Temp. aire admisión", "°C"),
            ("0x0B", "MAP", "map", "Presión múltiple", "kPa"),
            ("0x10", "MAF", "maf", "Flujo aire (MAF)", "g/s"),
            ("0x11", "THROTTLE", "tps", "Posición acelerador", "%"),
            ("0x0D", "SPEED", "speed_kmh", "Velocidad vehículo", "km/h"),
            ("0x5E", "FUEL_RATE", "fuel_rate", "Consumo combustible", "L/h"),
            ("0x2F", "FUEL_LEVEL", "fuel_level", "Nivel combustible", "%"),
            ("0x5C", "OIL_TEMP", "oil_temp", "Temperatura aceite", "°C"),
        ]
        
        # Construir payload de ejemplo
        cloud_interval = int(self.input_cloud_interval.text() or "1000")
        payload = {
            "id": self.input_device_id.text() or "OBD-DEVICE",
            "idc": self.input_car_id.text() or "CAR-001",
            "dt": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "d": self.chk_debug_mode.isChecked(),
            "s": {},
            "DTC": []
        }
        
        # Agregar PIDs OBD con valores de ejemplo
        example_values = {
            "rpm": 750.0,
            "batt_v": 13.8,
            "coolant_temp": 85.0,
            "load": 25.0,
            "engine.intake_temp": 35.0,
            "map": 101.3,
            "maf": 4.5,
            "tps": 15.0,
            "speed_kmh": 0.0,
            "fuel_rate": 1.2,
            "fuel_level": 65.0,
            "oil_temp": 90.0,
        }
        
        for pid_code, pid_name, cloud_key, desc, unit in OBD_PIDS_INFO:
            payload["s"][cloud_key] = {"v": example_values.get(cloud_key, 0.0)}
        
        # Agregar meta
        payload["s"]["wifi_rssi"] = {"v": -65}
        payload["s"]["heap_free"] = {"v": 180000}
        
        json_str = json.dumps(payload, indent=2)
        size_bytes = len(json.dumps(payload).encode('utf-8'))
        
        # Crear diálogo
        dialog = QDialog(self)
        dialog.setWindowTitle(f"OBD Payload Preview ({size_bytes} bytes)")
        dialog.resize(700, 600)
        layout = QVBoxLayout(dialog)
        
        # Información del sistema
        mqtt_server = self.input_mqtt_server.text()
        mqtt_topic = self.input_mqtt_topic.text()
        
        info_html = f"""
        <h3>📡 OBD Cloud Payload Preview</h3>
        <table style="border-collapse: collapse; width: 100%;">
            <tr><td><b>Device ID:</b></td><td>{payload['id']}</td></tr>
            <tr><td><b>Car ID:</b></td><td>{payload['idc']}</td></tr>
            <tr><td><b>Cloud Interval:</b></td><td>{cloud_interval} ms ({1000/cloud_interval:.1f} Hz)</td></tr>
            <tr><td><b>MQTT Server:</b></td><td>{mqtt_server}</td></tr>
            <tr><td><b>MQTT Topic:</b></td><td>{mqtt_topic}</td></tr>
            <tr><td><b>Payload Size:</b></td><td>{size_bytes} bytes</td></tr>
            <tr><td><b>Debug Mode:</b></td><td>{'SÍ (no guarda en BD)' if payload['d'] else 'NO (guarda en BD)'}</td></tr>
        </table>
        <br>
        <h4>📊 PIDs OBD Soportados (detectados automáticamente por ESP32-C3):</h4>
        <table style="border-collapse: collapse; width: 100%; font-size: 11px;">
            <tr style="background: #333; color: white;">
                <th style="padding: 4px; border: 1px solid #555;">PID</th>
                <th style="padding: 4px; border: 1px solid #555;">Nombre</th>
                <th style="padding: 4px; border: 1px solid #555;">Cloud Key</th>
                <th style="padding: 4px; border: 1px solid #555;">Descripción</th>
                <th style="padding: 4px; border: 1px solid #555;">Unidad</th>
            </tr>
        """
        
        for pid_code, pid_name, cloud_key, desc, unit in OBD_PIDS_INFO:
            info_html += f"""
            <tr>
                <td style="padding: 3px; border: 1px solid #444;">{pid_code}</td>
                <td style="padding: 3px; border: 1px solid #444;">{pid_name}</td>
                <td style="padding: 3px; border: 1px solid #444; font-family: monospace;">{cloud_key}</td>
                <td style="padding: 3px; border: 1px solid #444;">{desc}</td>
                <td style="padding: 3px; border: 1px solid #444;">{unit}</td>
            </tr>
            """
        
        info_html += "</table>"
        
        info_label = QLabel(info_html)
        info_label.setWordWrap(True)
        layout.addWidget(info_label)
        
        # JSON Preview
        layout.addWidget(QLabel("<b>JSON Payload (ejemplo):</b>"))
        text_edit = QTextEdit()
        text_edit.setPlainText(json_str)
        text_edit.setReadOnly(True)
        text_edit.setStyleSheet("font-family: Consolas, Monospace; font-size: 11px;")
        text_edit.setMaximumHeight(250)
        layout.addWidget(text_edit)
        
        # Nota
        note = QLabel("<i>Nota: Los valores mostrados son de ejemplo. Los valores reales se leen del ELM327 en tiempo real.</i>")
        note.setStyleSheet("color: #888; font-size: 10px;")
        layout.addWidget(note)
        
        dialog.exec()
    
    def show_memory_stats(self):
        """Muestra un diálogo con las estadísticas de memoria del ESP32."""
        self.status_label.setText("Obteniendo estadísticas de memoria...")
        QApplication.processEvents()
        
        stats = self.serial_manager.get_memory_stats()
        
        if stats is None:
            QMessageBox.warning(self, "Error", 
                "No se pudieron obtener las estadísticas de memoria.\n"
                "Verifica que el dispositivo esté conectado y tenga\n"
                "el firmware actualizado con soporte de monitoreo.")
            self.status_label.setText("Error obteniendo memoria")
            return
        
        # Calcular valores legibles
        free_kb = stats.get('free_heap', 0) / 1024
        total_kb = stats.get('heap_size', 0) / 1024
        min_kb = stats.get('min_free_heap', 0) / 1024
        max_alloc_kb = stats.get('max_alloc', 0) / 1024
        frag = stats.get('fragmentation', 0)
        sensors = stats.get('sensor_count', 0)
        uptime_s = stats.get('uptime_ms', 0) / 1000
        status = stats.get('status', 'UNKNOWN')
        
        # Formatear uptime legible
        hours = int(uptime_s // 3600)
        minutes = int((uptime_s % 3600) // 60)
        seconds = int(uptime_s % 60)
        uptime_str = f"{hours}h {minutes}m {seconds}s"
        
        # Determinar color del estado
        if status == "CRITICAL":
            status_color = "#e74c3c"  # Rojo
            status_icon = "🔴"
        elif status == "WARNING":
            status_color = "#f39c12"  # Naranja
            status_icon = "🟡"
        else:
            status_color = "#2ecc71"  # Verde
            status_icon = "🟢"
        
        # Calcular porcentaje de uso
        used_pct = ((total_kb - free_kb) / total_kb * 100) if total_kb > 0 else 0
        
        # Crear diálogo visual
        dialog = QDialog(self)
        dialog.setWindowTitle("📊 Estadísticas de Memoria - ESP32")
        dialog.setMinimumSize(400, 350)
        dialog.setStyleSheet("background-color: #2d2d2d; color: white;")
        
        layout = QVBoxLayout(dialog)
        layout.setSpacing(15)
        layout.setContentsMargins(20, 20, 20, 20)
        
        # Estado general
        status_label = QLabel(f"{status_icon} Estado: <b style='color:{status_color}'>{status}</b>")
        status_label.setStyleSheet("font-size: 18px;")
        layout.addWidget(status_label)
        
        # Barra de progreso visual de memoria
        progress_container = QWidget()
        progress_layout = QVBoxLayout(progress_container)
        progress_layout.setContentsMargins(0, 0, 0, 0)
        
        from PySide6.QtWidgets import QProgressBar
        progress = QProgressBar()
        progress.setRange(0, 100)
        progress.setValue(int(used_pct))
        progress.setFormat(f"Uso: {used_pct:.1f}% ({total_kb - free_kb:.1f} KB usados)")
        progress.setStyleSheet(f"""
            QProgressBar {{
                border: 2px solid #444;
                border-radius: 5px;
                text-align: center;
                height: 25px;
                font-size: 12px;
            }}
            QProgressBar::chunk {{
                background-color: {status_color};
            }}
        """)
        progress_layout.addWidget(progress)
        layout.addWidget(progress_container)
        
        # Grid de estadísticas
        stats_text = f"""
        <table style='width:100%; font-size:14px;'>
            <tr><td style='padding:5px;'>💾 <b>Memoria Libre:</b></td><td style='padding:5px;'>{free_kb:.1f} KB</td></tr>
            <tr><td style='padding:5px;'>📦 <b>Heap Total:</b></td><td style='padding:5px;'>{total_kb:.1f} KB</td></tr>
            <tr><td style='padding:5px;'>📉 <b>Mínimo Histórico:</b></td><td style='padding:5px;'>{min_kb:.1f} KB</td></tr>
            <tr><td style='padding:5px;'>🧱 <b>Bloque Máximo:</b></td><td style='padding:5px;'>{max_alloc_kb:.1f} KB</td></tr>
            <tr><td style='padding:5px;'>🔀 <b>Fragmentación:</b></td><td style='padding:5px;'>{frag:.1f}%</td></tr>
            <tr><td style='padding:5px;'>📡 <b>Sensores Config:</b></td><td style='padding:5px;'>{sensors}</td></tr>
            <tr><td style='padding:5px;'>⏱️ <b>Tiempo Encendido:</b></td><td style='padding:5px;'>{uptime_str}</td></tr>
        </table>
        """
        
        stats_label = QLabel(stats_text)
        stats_label.setStyleSheet("background-color: #1e1e1e; padding: 15px; border-radius: 8px;")
        layout.addWidget(stats_label)
        
        # Recomendaciones si hay problemas
        if status == "CRITICAL":
            warn_label = QLabel("⚠️ <b>ATENCIÓN:</b> La memoria está en nivel crítico.\n"
                               "Considera reducir el número de sensores configurados.")
            warn_label.setStyleSheet(f"color: {status_color}; padding: 10px;")
            warn_label.setWordWrap(True)
            layout.addWidget(warn_label)
        elif status == "WARNING":
            warn_label = QLabel("⚠️ La memoria está por debajo del nivel recomendado.\n"
                               "Monitorea el sistema durante uso prolongado.")
            warn_label.setStyleSheet(f"color: {status_color}; padding: 10px;")
            warn_label.setWordWrap(True)
            layout.addWidget(warn_label)
        
        # Botón cerrar
        btn_close = QPushButton("Cerrar")
        btn_close.setStyleSheet("background-color: #3498db; color: white; padding: 10px; border-radius: 5px;")
        btn_close.clicked.connect(dialog.accept)
        layout.addWidget(btn_close)
        
        self.status_label.setText("Memoria: " + status)
        dialog.exec()

    def toggle_simulation(self):
        self.simulation_active = not self.simulation_active
        if self.simulation_active:
            self.btn_simulate.setText("Detener")
            self.set_button_variant(self.btn_simulate, "danger")
            self.status_label.setText("Simulación activa")
        else:
            self.btn_simulate.setText("Simular")
            self.set_button_variant(self.btn_simulate, "accent")
            self.status_label.setText("Listo")

    def toggle_monitoring(self):
        if not self.serial_manager.is_connected:
            return

        if not hasattr(self, "monitoring"):
            self.monitoring = False
            
        if not self.monitoring:
            self.serial_manager.write("LIVE_ON")
            self.monitoring = True
            self.btn_monitor.setText("⏹ Stop")
            self.btn_monitor.setProperty("variant", "danger") # Use danger style for stop
            self.style().polish(self.btn_monitor) # Refresh style
            self.log_console("[INFO] Monitoring Started (LIVE_ON)")
            # Switch to Live tab
            self.tab_widget.setCurrentWidget(self.tab_live)
        else:
            self.serial_manager.write("LIVE_OFF")
            self.monitoring = False
            self.btn_monitor.setText("▶ Monitor")
            self.btn_monitor.setProperty("variant", "success")
            self.style().polish(self.btn_monitor)
            self.log_console("[INFO] Monitoring Stopped (LIVE_OFF)")
    
    def update_data(self):
        # Handle Serial Data
        self.read_serial_data()
        
        # Handle Simulation
        if self.simulation_active:
            self.generate_simulation_data()

    def generate_simulation_data(self):
        # Iterate over rows and update with random values
        for row in range(self.table.rowCount()):
            # Retrieve signal object from Channel column (Col.CHANNEL has signal in UserRole+1)
            signal_item = self.table.item(row, Col.CHANNEL)
            if signal_item is None:
                continue
            signal = signal_item.data(Qt.UserRole + 1)
            
            if signal:
                min_val = signal.minimum if signal.minimum is not None else 0
                max_val = signal.maximum if signal.maximum is not None else 100
                val = random.uniform(min_val, max_val)
                
                # FIX: Live Value is Col.LIVE_VALUE (15), not 14
                live_item = self.table.item(row, Col.LIVE_VALUE)
                if live_item:
                    live_item.setText(f"{val:.2f}")

    def read_serial_data(self):
        """Lee y procesa TODOS los datos disponibles del buffer serial.
        
        Esta función está optimizada para sistemas de telemetría en tiempo real:
        - Drena el buffer completo en cada ciclo (evita acumulación)
        - Usa blockSignals para evitar repintados innecesarios
        - Procesa múltiples updates en batch
        """
        if self.simulation_active:
            return  # Don't read serial if simulating

        # OPTIMIZACIÓN: Leer TODAS las líneas disponibles en el buffer
        lines = self.serial_manager.read_all_lines()
        if not lines:
            return
        
        # Acumular todos los valores de sensores de todas las líneas
        # Usamos el valor más reciente de cada sensor
        latest_values = {}
        
        for line in lines:
            try:
                # Expecting JSON: {"s": {"rpm": 3000, "temp": 90}}
                data = json.loads(line)
                
                if not isinstance(data, dict):
                    # If valid JSON but not a dict (e.g. integer 400), ignore or log
                    self.log_console(f"[DEVICE RAW]: {line}")
                    continue

                if "s" in data:
                    sensors = data["s"]
                    latest_values.update(sensors)  # Sobreescribe con valor más reciente
                    
                    # Log de diagnóstico OBD (mostrar en consola para visibilidad)
                    if "rpm" in sensors or "speed" in sensors or "temp" in sensors or "OBD_Status" in sensors:
                        log_parts = []
                        if "rpm" in sensors: log_parts.append(f"RPM={sensors['rpm']:.0f}")
                        if "speed" in sensors: log_parts.append(f"SPD={sensors['speed']:.0f}")
                        if "temp" in sensors: log_parts.append(f"TEMP={sensors['temp']:.0f}")
                        if "batt" in sensors: log_parts.append(f"BATT={sensors['batt']:.1f}")
                        if "OBD_Status" in sensors: log_parts.append(f"C3={'OK' if sensors['OBD_Status'] == 1.0 else 'DISC'}")
                        if log_parts:
                            self.log_console(f"[OBD DATA] {', '.join(log_parts)}")
                else:
                    # Log other JSON messages (Debug info, Memory stats, etc)
                    self.log_console(f"[DEVICE]: {line}")
            except json.JSONDecodeError:
                # Non-JSON lines might be CSV telemetry or debug/status messages
                stripped_line = line.strip()
                if stripped_line.startswith("LIVE:"):
                    # Format: LIVE:rpm,speed,coolant,throttle,lat,lng,ax,ay,az
                    try:
                        parts = stripped_line[5:].split(',')
                        if len(parts) >= 9:
                            # Map fixed positions to standardized sensor names
                            # These names MUST match the "Channel" or "Cloud ID" in your CAN config 
                            # if you want them to map to specific rows. 
                            # Or we can just use generic standard keys.
                            
                            # Standard keys expected by the UI or common sensors
                            latest_values["Engine_Speed"] = float(parts[0]) # RPM
                            latest_values["Vehicle_Speed"] = float(parts[1]) # Speed
                            latest_values["Coolant_Temp"] = float(parts[2])
                            latest_values["Throttle_Pos"] = float(parts[3])
                            latest_values["GPS_Lat"] = float(parts[4])
                            latest_values["GPS_Lng"] = float(parts[5])
                            latest_values["IMU_Accel_X"] = float(parts[6])
                            latest_values["IMU_Accel_Y"] = float(parts[7])
                            latest_values["IMU_Accel_Z"] = float(parts[8])
                            
                    except ValueError:
                        pass
                elif stripped_line:
                    self.log_console(f"[DEVICE]: {line}")
        
        if not latest_values:
            return

        # --- Update OBD Status Indicator ---
        if hasattr(self, "lbl_obd_status"):
            if "OBD_Status" in latest_values:
                status = latest_values["OBD_Status"]
                if status == 1.0:
                    self.lbl_obd_status.setText("OBD: CONNECTED")
                    self.lbl_obd_status.setStyleSheet("background-color: #27ae60; color: white; padding: 6px 10px; border-radius: 6px; font-weight: bold; margin-left: 15px;")
                else:
                    self.lbl_obd_status.setText("OBD: DISCONNECTED")
                    self.lbl_obd_status.setStyleSheet("background-color: #c0392b; color: white; padding: 6px 10px; border-radius: 6px; font-weight: bold; margin-left: 15px;")
            else:
                # Optional: Revert to N/A if signal strictly lost? Or keep last state? 
                # Keeping last state is usually better to avoid flickering.
                pass
        
        # OPTIMIZACIÓN: Bloquear señales durante actualización batch
        self.table.blockSignals(True)
        self.live_table.blockSignals(True)
        
        try:
            # Update main table
            for row in range(self.table.rowCount()):
                channel_item = self.table.item(row, Col.CHANNEL)
                if channel_item is None:
                    continue
                signal_name = channel_item.text()
                
                if signal_name in latest_values:
                    value = latest_values[signal_name]
                    # Formatear valor para mejor lectura
                    if isinstance(value, float):
                        value_str = f"{value:.4f}"
                    else:
                        value_str = str(value)
                    
                    # FIX: Live Value is Col.LIVE_VALUE (15), not 14
                    live_item = self.table.item(row, Col.LIVE_VALUE)
                    if live_item:
                        live_item.setText(value_str)
                        live_item.setForeground(QColor("#00ff00"))  # Visual feedback
                    
            # --- Update Dedicated Live Table (ALL DATA) ---
            # Iterate over ALL received keys to ensure everything is shown
            for key, value in latest_values.items():
                if isinstance(value, float):
                    value_str = f"{value:.4f}"
                else:
                    value_str = str(value)

                if key not in self.live_row_map:
                    # Add new row
                    row_idx = self.live_table.rowCount()
                    self.live_table.insertRow(row_idx)
                    self.live_table.setItem(row_idx, 0, QTableWidgetItem(str(key)))
                    
                    val_item = QTableWidgetItem(value_str)
                    val_item.setTextAlignment(Qt.AlignCenter)
                    val_item.setFont(QFont("Consolas", 12, QFont.Bold))
                    val_item.setForeground(QColor("#00ff00"))
                    self.live_table.setItem(row_idx, 1, val_item)
                    
                    self.live_row_map[key] = row_idx
                else:
                    # Update existing row
                    row_idx = self.live_row_map[key]
                    live_table_item = self.live_table.item(row_idx, 1)
                    if live_table_item:
                        live_table_item.setText(value_str)
            # Update dashboard cards
            if hasattr(self, "cards"):
                if "rpm" in latest_values: 
                    self.cards["rpm"].setText(f"{latest_values['rpm']:.0f}")
                if "speed" in latest_values: 
                    self.cards["speed"].setText(f"{latest_values['speed']:.0f}")
                if "temp" in latest_values: 
                    self.cards["temp"].setText(f"{latest_values['temp']:.0f}")
                if "batt" in latest_values: 
                    self.cards["batt"].setText(f"{latest_values['batt']:.1f}")
                if "fuel" in latest_values: 
                    self.cards["fuel"].setText(f"{latest_values['fuel']:.0f}")

        finally:
            # SIEMPRE desbloquear señales
            self.table.blockSignals(False)
            self.live_table.blockSignals(False)

if __name__ == "__main__":
    app = QApplication(sys.argv)
    window = MainWindow()
    window.show()
    sys.exit(app.exec())
