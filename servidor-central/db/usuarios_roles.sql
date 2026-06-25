--PostgreSQL 15+
--ejecutar como superusuario antes de desplegar FastAPI
--principio de minimos privilegios aplicado a los tres roles

SELECT 'CREATE DATABASE monitor_fastapi WITH ENCODING ''UTF8'''
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = 'monitor_fastapi'
)\gexec

--monitor_app: FastAPI en tiempo de ejecucion
DROP ROLE IF EXISTS monitor_app;
CREATE ROLE monitor_app WITH LOGIN PASSWORD 'REEMPLAZAR_CON_CONTRASENA_SEGURA_APP';

GRANT CONNECT ON DATABASE monitor_fastapi TO monitor_app;

\c monitor_fastapi

GRANT USAGE ON SCHEMA public TO monitor_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO monitor_app;
GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO monitor_app;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO monitor_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE ON SEQUENCES TO monitor_app;

--monitor_backup: exclusivo para el script de respaldos
DROP ROLE IF EXISTS monitor_backup;
CREATE ROLE monitor_backup WITH LOGIN PASSWORD 'REEMPLAZAR_CON_CONTRASENA_SEGURA_BACKUP';

GRANT CONNECT ON DATABASE monitor_fastapi TO monitor_backup;
GRANT USAGE ON SCHEMA public TO monitor_backup;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO monitor_backup;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT ON TABLES TO monitor_backup;

--monitor_dba: administrador para migraciones y mantenimiento
DROP ROLE IF EXISTS monitor_dba;
CREATE ROLE monitor_dba WITH LOGIN PASSWORD 'REEMPLAZAR_CON_CONTRASENA_SEGURA_DBA'
    CREATEDB;

GRANT ALL PRIVILEGES ON DATABASE monitor_fastapi TO monitor_dba;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO monitor_dba;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO monitor_dba;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON TABLES TO monitor_dba;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON SEQUENCES TO monitor_dba;

SELECT rolname, rolcanlogin, rolcreatedb
FROM pg_roles
WHERE rolname IN ('monitor_app', 'monitor_backup', 'monitor_dba')
ORDER BY rolname;
