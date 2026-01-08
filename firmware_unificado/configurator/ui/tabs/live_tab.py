
from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, QTableWidget, QTableWidgetItem, QHeaderView
)
from PySide6.QtCore import Qt
from PySide6.QtGui import QColor, QFont
from core.models import Col

class LiveTab(QWidget):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.live_row_map = {}
        self.cards = {}
        self.setup_ui()

    def setup_ui(self):
        layout = QVBoxLayout(self)
        
        # --- Dashboard Cards (V2 Style) ---
        cards_layout = QHBoxLayout()
        cards_layout.setSpacing(15)
        
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
        lbl_table = QLabel("Detalle de Sensores (TODOS los datos recibidos)")
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

    def update_values(self, latest_values):
        """Update the dashboard cards and the dedicated live table."""
        self.live_table.blockSignals(True)
        try:
            # Update dedicated live table
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
            self.live_table.blockSignals(False)
