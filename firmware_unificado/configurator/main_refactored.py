#!/usr/bin/env python3
"""
============================================================================
NEURONA OFF ROAD TELEMETRY - CONFIGURATOR
============================================================================

Production-Ready Desktop Configurator for ESP32 Telemetry System
Release Candidate 1.0 - December 2024

Features:
- Reactive UI based on data source mode (CAN/OBD/Hybrid/Tracking)
- Professional Dark Racing Theme
- Clean JSON generation without zombie data
- Real-time device communication

Usage:
    python main_refactored.py

Author: Neurona Racing Development
License: Proprietary
============================================================================
"""

import sys
import os

# Ensure the configurator directory is in path for imports
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from PySide6.QtWidgets import QApplication
from PySide6.QtGui import QFont
from PySide6.QtCore import Qt

from core.app_controller import AppController
from ui.main_window import MainWindow


def main():
    """Application entry point."""
    
    # Enable high DPI scaling for 4K monitors
    QApplication.setHighDpiScaleFactorRoundingPolicy(
        Qt.HighDpiScaleFactorRoundingPolicy.PassThrough
    )
    
    app = QApplication(sys.argv)
    
    # Application metadata
    app.setApplicationName("Neurona Telemetry Configurator")
    app.setOrganizationName("Neurona Racing")
    app.setOrganizationDomain("neurona.xyz")
    app.setApplicationVersion("1.0.0-RC1")
    
    # Set default font
    font = QFont("Segoe UI", 10)
    app.setFont(font)
    
    # Initialize the central controller
    controller = AppController()
    
    # Create and show main window
    window = MainWindow(controller)
    window.show()
    
    # Run event loop
    sys.exit(app.exec())


if __name__ == "__main__":
    main()
