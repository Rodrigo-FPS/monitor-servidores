--PostgreSQL 15+
--ejecutar como superusuario antes de desplegar Laravel
--principio de minimos privilegios aplicado a los tres roles
--roles con prefijo laravel_ para no colisionar con los de FastAPI en el mismo cluster
--creacion idempotente: el script puede re-ejecutarse sin error

SELECT 'CREATE DATABASE monitor_laravel WITH ENCODING ''UTF8'''
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = 'monitor_laravel'
)\gexec

--laravel_app: aplicacion web en tiempo de ejecucion
--permisos SELECT INSERT UPDATE DELETE sin poder modificar estructura
DO $$ BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'laravel_app') THEN
        CREATE ROLE laravel_app WITH LOGIN PASSWORD 'REEMPLAZAR_CON_CONTRASENA_SEGURA_APP';
    END IF;
END $$;
GRANT CONNECT ON DATABASE monitor_laravel TO laravel_app;

--laravel_backup: exclusivo para el script de respaldos (solo lectura)
DO $$ BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'laravel_backup') THEN
        CREATE ROLE laravel_backup WITH LOGIN PASSWORD 'REEMPLAZAR_CON_CONTRASENA_SEGURA_BACKUP';
    END IF;
END $$;
GRANT CONNECT ON DATABASE monitor_laravel TO laravel_backup;

--laravel_dba: administrador de BD para crear el esquema y mantenimiento
DO $$ BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'laravel_dba') THEN
        CREATE ROLE laravel_dba WITH LOGIN PASSWORD 'REEMPLAZAR_CON_CONTRASENA_SEGURA_DBA' CREATEDB;
    END IF;
END $$;
GRANT ALL PRIVILEGES ON DATABASE monitor_laravel TO laravel_dba;

\c monitor_laravel

GRANT USAGE ON SCHEMA public TO laravel_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO laravel_app;
GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO laravel_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO laravel_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE ON SEQUENCES TO laravel_app;

GRANT USAGE ON SCHEMA public TO laravel_backup;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO laravel_backup;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO laravel_backup;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT ON TABLES TO laravel_backup;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT ON SEQUENCES TO laravel_backup;

GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO laravel_dba;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO laravel_dba;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON TABLES TO laravel_dba;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON SEQUENCES TO laravel_dba;

SELECT rolname, rolcanlogin, rolcreatedb
FROM pg_roles
WHERE rolname IN ('laravel_app', 'laravel_backup', 'laravel_dba')
ORDER BY rolname;
