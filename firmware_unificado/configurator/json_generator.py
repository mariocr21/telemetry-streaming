import json

class JSONGenerator:
    @staticmethod
    def generate_config(messages, output_file=None, settings=None):
        """
        Convert a list of GenericMessage/Signal objects to the Neurona JSON format.
        
        Formato compatible con Motec:
        - offset: Byte de inicio (0, 2, 4, 6...)
        - length_bytes: Cantidad de bytes (1, 2, 4...)
        - mask: Máscara hexadecimal (ej: "FFFF" para 16 bits)
        
        El firmware también recibe start_bit y length (en bits) para compatibilidad.
        """
        config = {
            "sensors": []
        }
        
        if settings:
            # Mapear device/obd/imu/source/gps del nuevo schema
            cfg = {}
            # wifi/device quedan tal cual
            if "wifi" in settings:
                cfg["wifi"] = settings["wifi"]
            if "device" in settings:
                cfg["device"] = settings["device"]
            if "obd" in settings:
                cfg["obd"] = settings["obd"]
            # conservar cualquier otra clave para compatibilidad
            for k, v in settings.items():
                if k not in cfg:
                    cfg[k] = v
            config.update(cfg)
        
        for msg in messages:
            for signal in msg.signals:
                # Offset: El byte donde empieza el dato (formato Motec)
                # signal.start está en bits, dividimos por 8 para obtener bytes
                offset_bytes = signal.start // 8
                
                # Length: En bytes (formato Motec)
                # signal.length está en bits, dividimos por 8
                length_bytes = signal.length // 8
                if length_bytes == 0:
                    length_bytes = 1  # Mínimo 1 byte
                
                # Mask: Calculada a partir del length en bits
                mask_val = (1 << signal.length) - 1
                mask_hex = f"{mask_val:X}"
                
                sensor_entry = {
                    "name": signal.name,
                    "cloud_id": getattr(signal, 'cloud_id', signal.name),  # ID personalizado para cloud
                    "can_id": msg.frame_id,
                    
                    # Formato Motec (principal)
                    "offset": offset_bytes,           # Byte de inicio (0, 2, 4, 6...)
                    "length_bytes": length_bytes,     # Cantidad de bytes (1, 2, 4...)
                    "mask": mask_hex,                 # Máscara hex ("FFFF")
                    
                    # Campos para firmware (compatibilidad)
                    "start_byte": offset_bytes,       # Alias de offset
                    "start_bit": signal.start,        # Para Little Endian
                    "length": signal.length,          # En bits (firmware usa esto)
                    
                    # Otros campos
                    "signed": signal.is_signed,
                    "multiplier": float(signal.scale),
                    "offset_value": float(signal.offset),  # Renombrado para evitar confusión con offset de bytes
                    "byte_order": signal.byte_order,
                    
                    # Campos adicionales
                    "min": float(signal.minimum) if hasattr(signal, 'minimum') and signal.minimum is not None else 0.0,
                    "max": float(signal.maximum) if hasattr(signal, 'maximum') and signal.maximum is not None else 100.0,
                    "unit": signal.unit if hasattr(signal, 'unit') and signal.unit else ""
                }
                config["sensors"].append(sensor_entry)
        
        if output_file:
            with open(output_file, 'w') as f:
                json.dump(config, f, indent=2)
        
        return config


