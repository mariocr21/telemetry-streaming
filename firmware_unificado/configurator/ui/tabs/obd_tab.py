"""
============================================================================
OBD BRIDGE TAB - Production v1.0
============================================================================

Configuración exclusiva para modo OBD_BRIDGE (ESP32-C3 como puente UART).

SECCIONES:
1. Conexión UART (Principal ↔ C3): Pines RX/TX del ESP32 principal
2. WiFi del Puente (C3 → ELM327): Configuración que el Principal envía al C3

PURGED: Modo "direct" eliminado (hardware no soportado)

Author: Neurona Racing Development
Date: 2024-12-23
============================================================================
"""

from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QScrollArea, QFormLayout, QGroupBox, 
    QLineEdit, QSpinBox, QCheckBox, QComboBox, QPushButton, 
    QDoubleSpinBox, QLabel, QHBoxLayout, QFrame
)
from PySide6.QtCore import Signal


class ObdTab(QWidget):
    """
    OBD Bridge configuration tab.
    Only supports the ESP32-C3 bridge architecture.
    """
    preview_requested = Signal()

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

        layout = QVBoxLayout(content)
        layout.setContentsMargins(24, 24, 24, 24)
        layout.setSpacing(20)

        # ================================================================
        # HEADER - Mode indicator
        # ================================================================
        header_frame = QFrame()
        header_frame.setStyleSheet("""
            QFrame {
                background: qlineargradient(x1:0, y1:0, x2:1, y2:0,
                    stop:0 #1565c0, stop:1 #0d47a1);
                border-radius: 8px;
                padding: 12px;
            }
        """)
        header_layout = QHBoxLayout(header_frame)
        
        mode_label = QLabel("🔌 MODO: OBD BRIDGE (ESP32-C3)")
        mode_label.setStyleSheet("""
            color: white;
            font-size: 18px;
            font-weight: bold;
        """)
        header_layout.addWidget(mode_label)
        header_layout.addStretch()
        
        help_label = QLabel("El ESP32-C3 actúa como puente UART hacia el ELM327")
        help_label.setStyleSheet("color: #bbdefb; font-size: 12px;")
        header_layout.addWidget(help_label)
        
        layout.addWidget(header_frame)

        # ================================================================
        # SECTION 1: UART Bridge Connection (Principal ↔ C3)
        # ================================================================
        uart_group = QGroupBox("📡 Conexión UART (ESP32 Principal ↔ ESP32-C3)")
        uart_group.setStyleSheet("""
            QGroupBox {
                font-weight: bold;
                font-size: 14px;
                border: 2px solid #ff6b35;
                border-radius: 8px;
                margin-top: 16px;
                padding-top: 16px;
            }
            QGroupBox::title {
                subcontrol-origin: margin;
                padding: 4px 12px;
                background-color: #ff6b35;
                color: white;
                border-radius: 4px;
            }
        """)
        uart_layout = QFormLayout()
        uart_layout.setSpacing(12)

        # UART RX Pin
        self.input_uart_rx = QSpinBox()
        self.input_uart_rx.setRange(0, 40)
        self.input_uart_rx.setValue(32)
        self.input_uart_rx.setToolTip("GPIO del ESP32 Principal que RECIBE datos del C3")
        uart_layout.addRow("RX Pin (Principal recibe):", self.input_uart_rx)

        # UART TX Pin
        self.input_uart_tx = QSpinBox()
        self.input_uart_tx.setRange(0, 40)
        self.input_uart_tx.setValue(33)
        self.input_uart_tx.setToolTip("GPIO del ESP32 Principal que ENVÍA datos al C3")
        uart_layout.addRow("TX Pin (Principal envía):", self.input_uart_tx)

        # UART Baud Rate
        self.input_uart_baud = QComboBox()
        self.input_uart_baud.addItems(["115200", "230400", "460800", "921600"])
        self.input_uart_baud.setCurrentText("460800")
        self.input_uart_baud.setToolTip("Velocidad del enlace UART entre Principal y C3")
        uart_layout.addRow("Baud Rate:", self.input_uart_baud)

        uart_group.setLayout(uart_layout)
        layout.addWidget(uart_group)

        # ================================================================
        # SECTION 2: Bridge WiFi Config (C3 → ELM327)
        # ================================================================
        wifi_group = QGroupBox("📶 Ajustes WiFi del Puente (C3 → ELM327)")
        wifi_group.setStyleSheet("""
            QGroupBox {
                font-weight: bold;
                font-size: 14px;
                border: 2px solid #2196f3;
                border-radius: 8px;
                margin-top: 16px;
                padding-top: 16px;
            }
            QGroupBox::title {
                subcontrol-origin: margin;
                padding: 4px 12px;
                background-color: #2196f3;
                color: white;
                border-radius: 4px;
            }
        """)
        wifi_layout = QFormLayout()
        wifi_layout.setSpacing(12)

        # Info label
        wifi_info = QLabel(
            "Estos datos se envían al C3 para que se conecte al adaptador ELM327 WiFi.\n"
            "El ESP32-C3 usará estos parámetros para establecer conexión con el OBD."
        )
        wifi_info.setStyleSheet("color: #90caf9; font-size: 11px; margin-bottom: 10px;")
        wifi_info.setWordWrap(True)
        wifi_layout.addRow(wifi_info)

        # ELM WiFi SSID
        self.input_c3_elm_ssid = QLineEdit("WiFi_OBDII")
        self.input_c3_elm_ssid.setPlaceholderText("Nombre de la red WiFi del ELM327")
        self.input_c3_elm_ssid.setToolTip("SSID del adaptador ELM327 WiFi (ej: WiFi_OBDII, V-Link)")
        wifi_layout.addRow("SSID del ELM:", self.input_c3_elm_ssid)

        # ELM WiFi Password
        self.input_c3_elm_pass = QLineEdit("")
        self.input_c3_elm_pass.setPlaceholderText("Contraseña (dejar vacío si no tiene)")
        self.input_c3_elm_pass.setEchoMode(QLineEdit.Password)
        self.input_c3_elm_pass.setToolTip("Password del ELM327 (muchos no tienen)")
        wifi_layout.addRow("Password:", self.input_c3_elm_pass)

        # ELM IP Address
        self.input_c3_elm_ip = QLineEdit("192.168.0.10")
        self.input_c3_elm_ip.setPlaceholderText("192.168.0.10")
        self.input_c3_elm_ip.setToolTip("IP del adaptador ELM327 (típico: 192.168.0.10)")
        wifi_layout.addRow("IP del ELM:", self.input_c3_elm_ip)

        # ELM Port
        self.input_c3_elm_port = QLineEdit("35000")
        self.input_c3_elm_port.setPlaceholderText("35000")
        self.input_c3_elm_port.setToolTip("Puerto TCP del ELM327 (típico: 35000)")
        wifi_layout.addRow("Puerto:", self.input_c3_elm_port)

        wifi_group.setLayout(wifi_layout)
        layout.addWidget(wifi_group)

        # ================================================================
        # SECTION 3: OBD PIDs Configuration
        # ================================================================
        pids_group = QGroupBox("🔢 Configuración de PIDs OBD")
        pids_group.setStyleSheet("""
            QGroupBox {
                font-weight: bold;
                font-size: 14px;
                border: 2px solid #4caf50;
                border-radius: 8px;
                margin-top: 16px;
                padding-top: 16px;
            }
            QGroupBox::title {
                subcontrol-origin: margin;
                padding: 4px 12px;
                background-color: #4caf50;
                color: white;
                border-radius: 4px;
            }
        """)
        pids_layout = QFormLayout()
        pids_layout.setSpacing(12)

        # PIDs list
        self.input_obd_pids = QLineEdit("0x0C,0x0D,0x04,0x05,0x10,0x0B,0x11,BAT,0x5E,0x2F")
        self.input_obd_pids.setToolTip(
            "Lista de PIDs OBD a solicitar, separados por coma.\n"
            "Ejemplos:\n"
            "  0x0C = RPM\n"
            "  0x0D = Velocidad\n"
            "  0x05 = Temp. Coolant\n"
            "  0x04 = Carga Motor\n"
            "  BAT = Voltaje batería (especial)"
        )
        pids_layout.addRow("PIDs (hex, coma):", self.input_obd_pids)

        # Hidden fields to maintain compatibility (legacy from removed "direct" mode)
        self.chk_obd_enabled = QCheckBox()
        self.chk_obd_enabled.setChecked(True)
        self.chk_obd_enabled.setVisible(False)  # Always enabled in bridge mode
        
        self.combo_obd_mode = QComboBox()
        self.combo_obd_mode.addItem("c3_bridge")
        self.combo_obd_mode.setVisible(False)  # Always bridge mode

        pids_group.setLayout(pids_layout)
        layout.addWidget(pids_group)

        # ================================================================
        # SECTION 4: Fuel Calculation (Optional)
        # ================================================================
        fuel_group = QGroupBox("⛽ Cálculo de Combustible (Opcional)")
        fuel_group.setStyleSheet("""
            QGroupBox {
                font-weight: bold;
                font-size: 14px;
                border: 2px solid #ff9800;
                border-radius: 8px;
                margin-top: 16px;
                padding-top: 16px;
            }
            QGroupBox::title {
                subcontrol-origin: margin;
                padding: 4px 12px;
                background-color: #ff9800;
                color: #1a1a1a;
                border-radius: 4px;
            }
        """)
        fuel_layout = QFormLayout()
        fuel_layout.setSpacing(12)

        # Method
        self.combo_fuel_method = QComboBox()
        self.combo_fuel_method.addItems(["AUTO", "MAF", "MAP", "SPEED", "ECU"])
        self.combo_fuel_method.setCurrentText("AUTO")
        self.combo_fuel_method.setToolTip("Método para calcular consumo de combustible")
        fuel_layout.addRow("Método:", self.combo_fuel_method)

        # Engine Displacement
        self.input_engine_disp = QDoubleSpinBox()
        self.input_engine_disp.setRange(0.5, 10.0)
        self.input_engine_disp.setValue(2.0)
        self.input_engine_disp.setSingleStep(0.1)
        self.input_engine_disp.setSuffix(" L")
        self.input_engine_disp.setToolTip("Cilindrada del motor")
        fuel_layout.addRow("Cilindrada:", self.input_engine_disp)

        # Volumetric Efficiency
        self.input_ve_estimate = QDoubleSpinBox()
        self.input_ve_estimate.setRange(0.5, 1.5)
        self.input_ve_estimate.setValue(0.85)
        self.input_ve_estimate.setSingleStep(0.05)
        self.input_ve_estimate.setToolTip("Eficiencia volumétrica estimada")
        fuel_layout.addRow("VE Estimate:", self.input_ve_estimate)

        # Air-Fuel Ratio
        self.input_afr = QDoubleSpinBox()
        self.input_afr.setRange(10.0, 20.0)
        self.input_afr.setValue(14.7)
        self.input_afr.setSingleStep(0.1)
        self.input_afr.setToolTip("Relación aire/combustible objetivo")
        fuel_layout.addRow("AFR Target:", self.input_afr)

        fuel_group.setLayout(fuel_layout)
        layout.addWidget(fuel_group)

        # ================================================================
        # Preview Button
        # ================================================================
        btn_preview = QPushButton("👁️ Ver Payload OBD Bridge")
        btn_preview.setFixedHeight(48)
        btn_preview.setStyleSheet("""
            QPushButton {
                background: qlineargradient(x1:0, y1:0, x2:1, y2:0, 
                    stop:0 #2196f3, stop:1 #1976d2);
                color: white;
                font-size: 14px;
                font-weight: bold;
                border-radius: 8px;
            }
            QPushButton:hover {
                background: qlineargradient(x1:0, y1:0, x2:1, y2:0, 
                    stop:0 #42a5f5, stop:1 #2196f3);
            }
        """)
        btn_preview.clicked.connect(self.preview_requested.emit)
        layout.addWidget(btn_preview)

        # Spacer
        layout.addStretch()
