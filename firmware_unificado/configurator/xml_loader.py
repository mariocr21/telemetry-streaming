import xml.etree.ElementTree as ET
import re

class XmlLoader:
    """
    Parses generic XML files for CAN configuration (based on MoTeC schema).
    """
    @staticmethod
    def parse_file(file_path):
        try:
            tree = ET.parse(file_path)
            root = tree.getroot()
        except Exception as e:
            return False, f"XML Parse Error: {str(e)}"

        c_messages = {} # ID -> {name, id, signals}

        for mob in root.findall('mob'):
            try:
                base_id_hex = mob.get('canbusID')
                base_id = int(base_id_hex, 16)
            except:
                continue
            
            # Identify Set Name (e.g. "MotecM800Set1A")
            mob_type = mob.get('type', 'Unknown')
                
            for frame in mob.findall('frame'):
                try:
                    offset = int(frame.get('offset'))
                    frame_id = base_id + offset
                except:
                    continue
                
                # Group channels by ID
                if frame_id not in c_messages:
                    c_messages[frame_id] = {
                        "name": f"Msg_{frame_id:X}", 
                        "id": frame_id, 
                        "signals": []
                    }
                
                for channel in frame.findall('channel'):
                    # Parse channel attributes
                    c_name = channel.get('id', 'Unknown')
                    # Clean up name (remove c_ecu_ prefix if present)
                    c_name = c_name.replace("c_ecu_", "")
                    if channel.get('override'):
                        # Use override name if available as it is often friendlier
                        override = channel.get('override')
                        if override:
                             c_name = override.replace("ecu.", "").replace(".", "_")

                    c_type = channel.get('type', 'u16') # e.g. s16-be
                    byte_offset = int(channel.get('byteOffset', 0))
                    
                    # Scaling
                    multiplier = float(channel.get('multiplier', 1))
                    divider = float(channel.get('divider', 1))
                    offset_val = float(channel.get('offset', 0))
                    
                    scale = multiplier / divider
                    if divider == 0: scale = 1.0 # Safety
                    
                    # Type processing
                    length = 16
                    is_signed = False
                    byte_order = "little_endian"
                    
                    if "8" in c_type: length = 8
                    elif "16" in c_type: length = 16
                    elif "32" in c_type: length = 32
                    
                    if c_type.startswith("s"): is_signed = True
                    
                    if "be" in c_type: byte_order = "big_endian"
                    
                    # Create signal dict
                    sig = {
                        "name": c_name,
                        "start_byte": byte_offset,
                        "start_bit": byte_offset * 8,
                        "length": length,
                        "signed": is_signed,
                        "scale": scale,
                        "offset": offset_val,
                        "byte_order": byte_order,
                        "unit": channel.get('unit', "")
                    }
                    c_messages[frame_id]["signals"].append(sig)
        
        return True, list(c_messages.values())
