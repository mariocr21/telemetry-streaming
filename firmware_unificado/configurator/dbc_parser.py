import cantools

class DBCParser:
    def __init__(self):
        self.db = None

    def load_file(self, filepath):
        try:
            self.db = cantools.database.load_file(filepath, strict=False)
            return True, f"Loaded {len(self.db.messages)} messages."
        except Exception as e:
            return False, str(e)

    def get_messages(self):
        if not self.db:
            return []
        return self.db.messages

    def get_message_by_id(self, frame_id):
        if not self.db:
            return None
        return self.db.get_message_by_frame_id(frame_id)
