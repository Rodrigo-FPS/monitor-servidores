--motor PostgreSQL 15+
--encoding UTF-8

CREATE TABLE IF NOT EXISTS admins (
    id             BIGSERIAL    PRIMARY KEY,
    username       VARCHAR(64)  NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,   --hash bcrypt cost >= 12
    remember_token VARCHAR(100) DEFAULT NULL,
    created_at     TIMESTAMPTZ  DEFAULT NULL,
    updated_at     TIMESTAMPTZ  DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS servidores (
    server_id            VARCHAR(64)  PRIMARY KEY,  --solo alfanumericos guion y guion bajo
    hostname             VARCHAR(255) NOT NULL,
    ip_registrada        VARCHAR(45)  NOT NULL,     --IPv4 o IPv6 unica IP autorizada
    secreto              TEXT         NOT NULL,     --secreto HMAC de 64 hex chars mostrar solo al registrar
    estado               VARCHAR(20)  NOT NULL DEFAULT 'indeterminado',
    ultimo_visto         TIMESTAMPTZ  DEFAULT NULL,
    ultimo_tipo_mensaje  VARCHAR(20)  DEFAULT NULL,
    registrado_en        TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_servidores_estado       ON servidores (estado);
CREATE INDEX IF NOT EXISTS idx_servidores_ultimo_visto ON servidores (ultimo_visto);

CREATE TABLE IF NOT EXISTS intentos_login_fallidos (
    id         BIGSERIAL   PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    username   VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_intentos_ip_created ON intentos_login_fallidos (ip, created_at); --indice para contar intentos por IP en ventana de tiempo

CREATE TABLE IF NOT EXISTS sessions (
    id            VARCHAR(255) PRIMARY KEY,
    user_id       BIGINT       DEFAULT NULL,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    user_agent    TEXT         DEFAULT NULL,
    payload       TEXT         NOT NULL,
    last_activity INTEGER      NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_sessions_user_id       ON sessions (user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_last_activity ON sessions (last_activity);
