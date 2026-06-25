--PostgreSQL 15+
--ejecutar como superusuario antes de desplegar FastAPI
--principio de minimos privilegios aplicado a los tres roles
--roles con prefijo fastapi_ para no colisionar con los de Laravel en el mismo cluster
--creacion idempotente: el script puede re-ejecutarse sin error

SELECT 'CREATE DATABASE monitor_fastapi WITH ENCODING ''UTF8'''
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = 'monitor_fastapi'
)\gexec

--fastapi_app: FastAPI en tiempo de ejecucion (sin DDL)
DO $$ BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'fastapi_app') THEN
        CREATE ROLE fastapi_app WITH LOGIN PASSWORD 'REEMPLAZAR_CON_CONTRASENA_SEGURA_APP';
    END IF;
END $$;
GRANT CONNECT ON DATABASE monitor_fastapi TO fastapi_app;

--fastapi_backup: exclusivo para el script de respaldos (solo lectura)
DO $$ BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'fastapi_backup') THEN
        CREATE ROLE fastapi_backup WITH LOGIN PASSWORD 'REEMPLAZAR_CON_CONTRASENA_SEGURA_BACKUP';
    END IF;
END $$;
GRANT CONNECT ON DATABASE monitor_fastapi TO fastapi_backup;

--fastapi_dba: administrador de BD para crear el esquema y mantenimiento
DO $$ BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'fastapi_dba') THEN
        CREATE ROLE fastapi_dba WITH LOGIN PASSWORD 'REEMPLAZAR_CON_CONTRASENA_SEGURA_DBA' CREATEDB;
    END IF;
END $$;
GRANT ALL PRIVILEGES ON DATABASE monitor_fastapi TO fastapi_dba;

\c monitor_fastapi

GRANT USAGE ON SCHEMA public TO fastapi_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO fastapi_app;
GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO fastapi_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO fastapi_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE ON SEQUENCES TO fastapi_app;

GRANT USAGE ON SCHEMA public TO fastapi_backup;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO fastapi_backup;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO fastapi_backup;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT ON TABLES TO fastapi_backup;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT ON SEQUENCES TO fastapi_backup;

GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO fastapi_dba;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO fastapi_dba;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON TABLES TO fastapi_dba;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON SEQUENCES TO fastapi_dba;

SELECT rolname, rolcanlogin, rolcreatedb
FROM pg_roles
WHERE rolname IN ('fastapi_app', 'fastapi_backup', 'fastapi_dba')
ORDER BY rolname;
