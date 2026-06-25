--BD de Laravel: autenticacion sesiones e intentos de login
--motor PostgreSQL 15+
--encoding UTF-8

CREATE TABLE IF NOT EXISTS admins (
    id             BIGSERIAL    PRIMARY KEY,
    username       VARCHAR(64)  NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,   --hash bcrypt cost >= 12
    rol            VARCHAR(20)  NOT NULL DEFAULT 'usuario',  --minimo privilegio por defecto
    remember_token VARCHAR(100) DEFAULT NULL,
    created_at     TIMESTAMPTZ  DEFAULT NULL,
    updated_at     TIMESTAMPTZ  DEFAULT NULL,
    CONSTRAINT chk_admins_rol CHECK (rol IN ('admin','usuario'))
);

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
