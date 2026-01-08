
import os
from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, QLineEdit, QCheckBox, 
    QPushButton, QTableWidget, QTableWidgetItem, QHeaderView, QMessageBox,
    QDialog, QFormLayout, QSpinBox, QDoubleSpinBox, QComboBox, QDialogButtonBox,
    QFileDialog
)
from PySide6.QtCore import Qt, Signal
import random 

# Adjust imports based on project structure
# Assuming running from root
from core.models import Col, GenericMessage, GenericSignal
from dbc_parser import DBCParser
from xml_loader import XmlLoader

class CanTab(QWidget):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.parser = DBCParser()
        self.setup_ui()
        
    def setup_ui(self):
        layout = QVBoxLayout(self)

        # ===== Toolbar local =====
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
        # Using inline styling to avoid dependency on global styles for now, 
        # but eventually should use global QSS or specific styles
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
        self.btn_clear_all.setProperty("variant", "danger")
        self.btn_clear_all.clicked.connect(self.clear_table)
        top_bar.addWidget(self.btn_clear_all)

        layout.addLayout(top_bar)

        # ===== Tabla =====
        self.table = QTableWidget()
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

        # Default: vista básica
        self.set_can_columns_mode(False)

    def on_table_item_changed(self, item):
        self.table.blockSignals(True)
        try:
            row = item.row()
            col = item.column()
            
            # Multiplier changed -> Update Divisor
            if col == Col.MULTIPLIER:
                try:
                    text = item.text().replace(',', '.')
                    factor = float(text)
                    if factor != 0:
                        divisor = 1.0 / factor
                        div_str = str(int(divisor)) if divisor.is_integer() else f"{divisor:.6g}"
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
                        fac_str = str(int(factor)) if factor.is_integer() else f"{factor:.6g}"
                        self.table.item(row, Col.MULTIPLIER).setText(fac_str)
                    else:
                        self.table.item(row, Col.MULTIPLIER).setText("Inf")
                except ValueError:
                    pass
            
            # Byte Order changed
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
        basic_cols = {Col.ENABLE, Col.CAN_ID, Col.CHANNEL, Col.CLOUD_ID, Col.LIVE_VALUE}
        for col in range(self.table.columnCount()):
            is_basic = col in basic_cols
            self.table.setColumnHidden(col, (not advanced) and (not is_basic))

    def clear_table(self):
        confirm = QMessageBox.question(
            self, "Confirmar", 
            "¿Estás seguro de que quieres borrar todos los sensores de la tabla?",
            QMessageBox.Yes | QMessageBox.No
        )
        if confirm == QMessageBox.Yes:
            self.table.setRowCount(0)

    def filter_can_table(self, text: str):
        query = (text or "").strip().lower()
        if not query:
            for row in range(self.table.rowCount()):
                self.table.setRowHidden(row, False)
            return

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

    def load_dbc(self):
        file_path, _ = QFileDialog.getOpenFileName(self, "Open DBC File", "", "DBC Files (*.dbc);;All Files (*)")
        if file_path:
            success, message = self.parser.load_file(file_path)
            if success:
                # self.status_label.setText(f"Loaded: {os.path.basename(file_path)}") # Can't access status label easily
                QMessageBox.information(self, "DBC Loaded", f"Loaded: {os.path.basename(file_path)}")
                self.populate_table()
            else:
                QMessageBox.warning(self, "Error", f"Error: {message}")

    def import_xml_config(self):
        file_path, _ = QFileDialog.getOpenFileName(self, "Import XML Config", "", "XML Files (*.xml);;All Files (*)")
        if not file_path: return
        
        success, result = XmlLoader.parse_file(file_path)
        if success:
            messages = result
            self.table.setRowCount(0)
            
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
            
            QMessageBox.information(self, "Import XML", f"Imported XML: {os.path.basename(file_path)}")
        else:
            QMessageBox.critical(self, "Import Error", result)

    def populate_table(self):
        messages = self.parser.get_messages()
        self.table.setRowCount(0)
        for msg in messages:
            for signal in msg.signals:
                self.add_signal_row(msg, signal)

    def add_signal_row(self, msg, signal, cloud_id=None):
        self.table.blockSignals(True)
        row = self.table.rowCount()
        self.table.insertRow(row)
        
        # 0: Enable
        check_item = QTableWidgetItem()
        check_item.setFlags(Qt.ItemIsUserCheckable | Qt.ItemIsEnabled)
        check_item.setCheckState(Qt.Checked)
        self.table.setItem(row, 0, check_item)

        # 1: ID
        id_item = QTableWidgetItem(f"0x{msg.frame_id:X}")
        self.table.setItem(row, 1, id_item)
        
        # 2: Channel
        sig_item = QTableWidgetItem(signal.name)
        sig_item.setData(Qt.UserRole, msg)
        sig_item.setData(Qt.UserRole + 1, signal)
        self.table.setItem(row, 2, sig_item)
        
        # 3: Cloud ID
        cloud_id_value = cloud_id if cloud_id else signal.name
        cloud_id_item = QTableWidgetItem(cloud_id_value)
        cloud_id_item.setToolTip("ID personalizado para usar en el JSON de la nube")
        self.table.setItem(row, 3, cloud_id_item)
        
        # Calculations
        offset_byte = signal.start // 8
        len_byte = (signal.length + 7) // 8
        mask_val = (1 << signal.length) - 1
        mask_str = f"{mask_val:X}"
        
        self.table.setItem(row, 4, QTableWidgetItem(str(offset_byte)))
        self.table.setItem(row, 5, QTableWidgetItem(str(len_byte)))
        self.table.setItem(row, 6, QTableWidgetItem(mask_str))
        self.table.setItem(row, 7, QTableWidgetItem("Signed" if signal.is_signed else "Unsigned"))
        self.table.setItem(row, 8, QTableWidgetItem(str(signal.scale)))
        
        # Divisor
        try:
            factor = float(signal.scale)
            if factor != 0:
                divisor = 1.0 / factor
                div_str = str(int(divisor)) if divisor.is_integer() else f"{divisor:.6g}"
            else:
                div_str = "Inf"
        except:
            div_str = "-"
        self.table.setItem(row, 9, QTableWidgetItem(div_str))

        self.table.setItem(row, 10, QTableWidgetItem(str(signal.offset)))
        self.table.setItem(row, 11, QTableWidgetItem(str(signal.minimum)))
        self.table.setItem(row, 12, QTableWidgetItem(str(signal.maximum)))
        self.table.setItem(row, 13, QTableWidgetItem(str(signal.unit) if signal.unit else ""))
        
        byte_order_str = "Big Endian" if signal.byte_order == "big_endian" else "Little Endian"
        byte_order_item = QTableWidgetItem(byte_order_str)
        byte_order_item.setData(Qt.UserRole, signal.byte_order)
        self.table.setItem(row, 14, byte_order_item)
        
        val_item = QTableWidgetItem("-")
        val_item.setTextAlignment(Qt.AlignCenter)
        self.table.setItem(row, 15, val_item)
        
        self.table.blockSignals(False)

    def add_manual_sensor_dialog(self):
        dialog = QDialog(self)
        dialog.setWindowTitle("Add Manual Sensor (Motec)")
        form = QFormLayout(dialog)
        
        inp_name = QLineEdit()
        inp_can_id = QLineEdit()
        inp_offset = QSpinBox(); inp_offset.setRange(0, 63)
        inp_len_byte = QSpinBox(); inp_len_byte.setRange(1, 8); inp_len_byte.setValue(2)
        inp_mask = QLineEdit("FFFF")
        inp_signed = QCheckBox("Signed")
        inp_multiplier = QDoubleSpinBox(); inp_multiplier.setValue(1.0); inp_multiplier.setRange(-1e6, 1e6)
        inp_adder = QDoubleSpinBox(); inp_adder.setRange(-1e6, 1e6)
        inp_min = QDoubleSpinBox(); inp_min.setRange(-1e6, 1e6)
        inp_max = QDoubleSpinBox(); inp_max.setRange(-1e6, 1e6); inp_max.setValue(100)
        
        inp_byte_order = QComboBox()
        inp_byte_order.addItems(["Big Endian (Motec)", "Little Endian (Intel)"])
        
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
                frame_id = int(can_id_str, 16)
                name = inp_name.text() or f"Sensor_{frame_id:X}"
                offset_byte = inp_offset.value()
                len_byte = inp_len_byte.value()
                
                start_bit = offset_byte * 8
                length_bit = len_byte * 8
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

