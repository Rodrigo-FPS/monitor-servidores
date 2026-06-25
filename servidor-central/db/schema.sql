--BD de FastAPI: servidores monitoreados
--motor PostgreSQL 15+
--encoding UTF-8

CREATE TABLE IF NOT EXISTS servidores (
    server_id           VARCHAR(64)  PRIMARY KEY,  --solo alfanumericos guion y guion bajo
    hostname            VARCHAR(255) NOT NULL,
    ip_registrada       VARCHAR(45)  NOT NULL,     --IPv4 o IPv6 unica IP autorizada para enviar latidos
    clave_publica       TEXT         NOT NULL,     --clave publica Ed25519 (PEM) usada para verificar la firma de cada latido
    estado              VARCHAR(20)  NOT NULL DEFAULT 'indeterminado',
    ultimo_visto        TIMESTAMPTZ  DEFAULT NULL,
    ultimo_tipo_mensaje VARCHAR(20)  DEFAULT NULL,
    registrado_en       TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_servidores_estado       ON servidores (estado);
CREATE INDEX IF NOT EXISTS idx_servidores_ultimo_visto ON servidores (ultimo_visto);
CREATE INDEX IF NOT EXISTS idx_servidores_hostname     ON servidores (hostname);
