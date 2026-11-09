"""
Lexus voice server: turns text into a natural male voice (Piper "Ryan", through sherpa-onnx) and returns a WAV.
It keeps the voice loaded in memory so an answer takes about a second instead of seven. NEXUS-L (TtsController) is its only client.
Listens on 127.0.0.1:5061 (this PC only).   GET /tts?text=...   GET /health
Start: pythonw tools\\tts\\tts_server.py        (a scheduled task starts it at logon)
"""
import io
import os
import sys
import threading
import wave
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs

import sherpa_onnx

HERE = os.path.dirname(os.path.abspath(__file__))
VOICE = os.path.normpath(os.path.join(HERE, "..", "..", "writable", "tts", "voice-medium"))
PORT = 5061

cfg = sherpa_onnx.OfflineTtsConfig(
    model=sherpa_onnx.OfflineTtsModelConfig(
        vits=sherpa_onnx.OfflineTtsVitsModelConfig(
            model=os.path.join(VOICE, "en_US-ryan-medium.onnx"),
            tokens=os.path.join(VOICE, "tokens.txt"),
            data_dir=os.path.join(VOICE, "espeak-ng-data"),
        ),
        num_threads=4,
        provider="cpu",
    ),
)
TTS = sherpa_onnx.OfflineTts(cfg)
LOCK = threading.Lock()   # one sentence at a time keeps the CPU for it


def wav_bytes(text: str) -> bytes:
    with LOCK:
        audio = TTS.generate(text, sid=0, speed=1.0)
    pcm = bytearray()
    for s in audio.samples:
        v = max(-1.0, min(1.0, s))
        pcm += int(v * 32767).to_bytes(2, "little", signed=True)
    buf = io.BytesIO()
    with wave.open(buf, "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(audio.sample_rate)
        w.writeframes(bytes(pcm))
    return buf.getvalue()


class H(BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def do_GET(self):
        u = urlparse(self.path)
        if u.path == "/health":
            body, code, ctype = b'{"ok":true}', 200, "application/json"
        elif u.path == "/tts":
            text = (parse_qs(u.query).get("text") or [""])[0].strip()[:500]
            if not text:
                body, code, ctype = b'{"ok":false}', 422, "application/json"
            else:
                try:
                    body, code, ctype = wav_bytes(text), 200, "audio/wav"
                except Exception as e:  # never take the server down for one bad sentence
                    body, code, ctype = ('{"ok":false,"error":"%s"}' % str(e).replace('"', "'")).encode(), 500, "application/json"
        else:
            body, code, ctype = b"{}", 404, "application/json"
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)


if __name__ == "__main__":
    ThreadingHTTPServer(("127.0.0.1", PORT), H).serve_forever()
