import sys
import os
import stat
import signal
import time
import base64
from datetime import datetime, timezone

import httpx
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from cryptography.hazmat.primitives.serialization import (
    Encoding, PublicFormat, PrivateFormat, NoEncryption, load_pem_private_key
)


RUTA_CONFIG        = "/etc/monitor-agent/config.env"
RUTA_CLAVE_PRIVADA = "/etc/monitor-agent/private.key"
RUTA_CLAVE_PUBLICA = "/etc/monitor-agent/public.key"


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


def verificar_permisos_archivo(ruta, permisos_requeridos, nombre):
    info = os.stat(ruta)
    permisos = stat.S_IMODE(info.st_mode)
    if permisos != permisos_requeridos:
        raise PermissionError(
            f"{nombre} tiene permisos {oct(permisos)} deben ser {oct(permisos_requeridos)}\n"
            f"ejecuta: sudo chmod {oct(permisos_requeridos)[2:]} {ruta}"
        )


def generar_o_cargar_claves():
    if not os.path.exists(RUTA_CLAVE_PRIVADA):
        clave_privada = Ed25519PrivateKey.generate()

        privada_pem = clave_privada.private_bytes(Encoding.PEM, PrivateFormat.PKCS8, NoEncryption())
        publica_pem = clave_privada.public_key().public_bytes(Encoding.PEM, PublicFormat.SubjectPublicKeyInfo)

        # Crear clave privada con permisos 400 desde el principio
        fd = os.open(RUTA_CLAVE_PRIVADA, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o400)
        with os.fdopen(fd, "wb") as f:
            f.write(privada_pem)

        with open(RUTA_CLAVE_PUBLICA, "wb") as f:
            f.write(publica_pem)

        print("[INFO] Par de claves Ed25519 generado.")
        print("[ACCION] Registra este servidor en el panel con la siguiente clave publica:")
        print("=" * 64)
        print(publica_pem.decode("utf-8").strip())
        print("=" * 64)

    verificar_permisos_archivo(RUTA_CLAVE_PRIVADA, 0o400, "clave privada")

    with open(RUTA_CLAVE_PRIVADA, "rb") as f:
        return load_pem_private_key(f.read(), password=None)


def construir_cabeceras(server_id, clave_privada):
    timestamp_iso = datetime.now(timezone.utc).isoformat()
    mensaje       = f"{server_id}:{timestamp_iso}".encode("utf-8")
    firma_b64     = base64.b64encode(clave_privada.sign(mensaje)).decode("ascii")
    return {
        "Authorization": f"Ed25519 {firma_b64}",
        "X-Server-ID":   server_id,
        "X-Timestamp":   timestamp_iso,
    }


def enviar_peticion(cliente_http, url, server_id, clave_privada, tipo, max_intentos=3):
    for intento in range(max_intentos):
        cabeceras = construir_cabeceras(server_id, clave_privada)
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
            espera = 2 ** (intento + 1)
            print(f"[REINTENTO] esperando {espera}s...")
            time.sleep(espera)

    print(f"[FALLO] no se pudo enviar {tipo} despues de {max_intentos} intentos")
    return False


def main():
    try:
        clave_privada = generar_o_cargar_claves()
    except PermissionError as e:
        print(f"[ERROR] {e}")
        sys.exit(1)
    except Exception as e:
        print(f"[ERROR] al cargar claves Ed25519: {e}")
        sys.exit(1)

    try:
        verificar_permisos_archivo(RUTA_CONFIG, 0o600, "config.env")
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

    server_id    = config.get("SERVER_ID", "").strip()
    server_url   = config.get("SERVER_URL", "").strip()
    intervalo    = int(config.get("INTERVALO_SEGUNDOS", "30"))
    permitir_http = config.get("PERMITIR_HTTP", "0").strip() == "1"
    verificar_ssl = config.get("VERIFICAR_SSL", "1").strip() != "0"

    if not server_id or not server_url:
        print("[ERROR] faltan SERVER_ID o SERVER_URL en la configuracion")
        sys.exit(1)

    #El PDF exige transmisiones cifradas: se rechaza HTTP por defecto. Solo entornos
    #de prueba controlados pueden permitirlo con PERMITIR_HTTP=1 en la config.
    if not server_url.startswith("https://"):
        if permitir_http:
            print(f"[ADVERTENCIA] SERVER_URL sin HTTPS permitido por PERMITIR_HTTP=1: {server_url}")
        else:
            print(f"[ERROR] SERVER_URL debe usar HTTPS: {server_url}")
            print("[ERROR] para pruebas locales sin TLS, anade PERMITIR_HTTP=1 en config.env")
            sys.exit(1)

    url_heartbeat = server_url.rstrip("/") + "/api/heartbeat"
    url_shutdown  = server_url.rstrip("/") + "/api/shutdown"

    if not verificar_ssl:
        print("[ADVERTENCIA] verificacion SSL deshabilitada (certificado autofirmado)")

    print(f"[INFO] demonio iniciado ID: {server_id}")
    print(f"[INFO] servidor: {server_url}  intervalo: {intervalo}s")

    seguir_corriendo = [True]

    def manejar_apagado(signum, frame):
        print("[INFO] senal de apagado recibida")
        seguir_corriendo[0] = False

    signal.signal(signal.SIGTERM, manejar_apagado)
    signal.signal(signal.SIGINT, manejar_apagado)

    with httpx.Client(verify=verificar_ssl) as cliente_http:
        while seguir_corriendo[0]:
            enviar_peticion(cliente_http, url_heartbeat, server_id, clave_privada, "heartbeat")

            for _ in range(intervalo):
                if not seguir_corriendo[0]:
                    break
                time.sleep(1)

        print("[INFO] enviando notificacion de apagado al servidor...")
        enviar_peticion(cliente_http, url_shutdown, server_id, clave_privada, "shutdown")

    print("[INFO] demonio terminado")


if __name__ == "__main__":
    main()
