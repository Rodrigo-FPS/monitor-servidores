import sys
import os
import stat
import signal
import time
import hmac
import hashlib
from datetime import datetime, timezone

import httpx


RUTA_CONFIG = "/etc/monitor-agent/config.env"


def load_config(path):
    config = {}
    with open(path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if "=" not in line:
                raise ValueError(f"linea invalida en config: {line}")
            key, value = line.split("=", 1)
            config[key.strip()] = value.strip()
    return config


def verificar_permisos(ruta):
    info = os.stat(ruta)
    permisos = stat.S_IMODE(info.st_mode)
    if permisos != 0o600: #permisos 600 obligatorios para proteger el secreto HMAC
        raise PermissionError(
            f"el archivo {ruta} tiene permisos {oct(permisos)} deben ser 600\n"
            f"ejecuta: sudo chmod 600 {ruta}"
        )


def calcular_hmac(secreto_bytes, server_id, timestamp_iso):
    mensaje = f"{server_id}:{timestamp_iso}".encode("utf-8")
    firma = hmac.new(secreto_bytes, mensaje, hashlib.sha256)
    return firma.hexdigest()


def construir_cabeceras(server_id, secreto_bytes):
    timestamp_iso = datetime.now(timezone.utc).isoformat() #timestamp fresco en cada llamada para el antireplay
    firma = calcular_hmac(secreto_bytes, server_id, timestamp_iso)
    return {
        "Authorization": f"HMAC {firma}",
        "X-Server-ID":   server_id,
        "X-Timestamp":   timestamp_iso,
    }


def enviar_peticion(cliente_http, url, server_id, secreto_bytes, tipo, max_intentos=3):
    for intento in range(max_intentos):
        cabeceras = construir_cabeceras(server_id, secreto_bytes)
        try:
            respuesta = cliente_http.post(url, headers=cabeceras, timeout=10)
            print(f"[OK] {tipo} enviado HTTP {respuesta.status_code}")
            return True
        except httpx.ConnectError:
            print(f"[ERROR] no se pudo conectar: {url}")
        except httpx.TimeoutException:
            print("[ERROR] timeout al conectar con el servidor")
        except Exception as error:
            print(f"[ERROR] error inesperado: {error}")

        if intento < max_intentos - 1:
            espera = 2 ** (intento + 1) #backoff exponencial entre reintentos
            print(f"[REINTENTO] esperando {espera}s...")
            time.sleep(espera)

    print(f"[FALLO] no se pudo enviar {tipo} despues de {max_intentos} intentos")
    return False


def main():
    try:
        verificar_permisos(RUTA_CONFIG)
        config = load_config(RUTA_CONFIG)
    except FileNotFoundError:
        print(f"[ERROR] no se encontro: {RUTA_CONFIG}")
        sys.exit(1)
    except PermissionError as e:
        print(f"[ERROR] {e}")
        sys.exit(1)
    except Exception as e:
        print(f"[ERROR] al leer configuracion: {e}")
        sys.exit(1)

    server_id      = config.get("SERVER_ID", "").strip()
    secreto_texto  = config.get("SECRETO", "").strip()
    server_url     = config.get("SERVER_URL", "").strip()
    intervalo      = int(config.get("INTERVALO_SEGUNDOS", "30"))

    if not server_id or not secreto_texto or not server_url:
        print("[ERROR] faltan SERVER_ID SECRETO o SERVER_URL en la configuracion")
        sys.exit(1)

    if not server_url.startswith("https://"):
        print(f"[ADVERTENCIA] SERVER_URL no usa HTTPS: {server_url}")

    secreto_bytes = secreto_texto.encode("utf-8")
    del secreto_texto #borrar el string para no dejarlo en memoria

    url_heartbeat = server_url.rstrip("/") + "/api/heartbeat"
    url_shutdown  = server_url.rstrip("/") + "/api/shutdown"

    print(f"[INFO] demonio iniciado ID: {server_id}")
    print(f"[INFO] servidor: {server_url}  intervalo: {intervalo}s")

    seguir_corriendo = [True]

    def manejar_apagado(signum, frame):
        print("[INFO] senal de apagado recibida")
        seguir_corriendo[0] = False

    signal.signal(signal.SIGTERM, manejar_apagado)
    signal.signal(signal.SIGINT, manejar_apagado)

    with httpx.Client() as cliente_http:
        while seguir_corriendo[0]:
            enviar_peticion(cliente_http, url_heartbeat, server_id, secreto_bytes, "heartbeat")

            for _ in range(intervalo): #sleep en trozos de 1s para reaccionar rapido a senales
                if not seguir_corriendo[0]:
                    break
                time.sleep(1)

        print("[INFO] enviando notificacion de apagado al servidor...")
        enviar_peticion(cliente_http, url_shutdown, server_id, secreto_bytes, "shutdown")

    print("[INFO] demonio terminado")


if __name__ == "__main__":
    main()
