
from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, QPushButton, QTextEdit
)
from PySide6.QtGui import QColor

class ConsoleWidget(QWidget):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.setup_ui()

    def setup_ui(self):
        console_layout = QVBoxLayout(self)
        console_layout.setContentsMargins(0, 0, 0, 0)

        console_header = QHBoxLayout()
        console_header.addWidget(QLabel("Consola serial:"))

        self.btn_clear_console = QPushButton("Limpiar")
        self.btn_clear_console.setFixedSize(70, 26)
        self.btn_clear_console.clicked.connect(self.clear)
        console_header.addWidget(self.btn_clear_console)
        console_header.addStretch()

        console_layout.addLayout(console_header)

        self.output = QTextEdit()
        self.output.setReadOnly(True)
        self.output.setStyleSheet("background-color: #1e1e1e; color: #00ff00; font-family: Consolas, Monospace;")
        console_layout.addWidget(self.output)

    def clear(self):
        self.output.clear()

    def log(self, text):
        self.output.append(text)
        # Auto scroll relative to the VScrollBar
        sb = self.output.verticalScrollBar()
        sb.setValue(sb.maximum())
