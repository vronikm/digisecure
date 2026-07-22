-- =====================================================================
-- DigiSecure - Migracion 032: datos familiares de la ficha
-- =====================================================================

-- @up

CREATE TABLE fichas_familiares (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    ficha_id CHAR(26) NOT NULL,
    parentesco ENUM('PADRE','MADRE','HERMANO','CONYUGE') NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    direccion VARCHAR(255) NULL,
    telefono VARCHAR(30) NULL,
    cedula_hash CHAR(64) NULL,
    cedula_cifrada VARBINARY(1024) NULL,
    edad TINYINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_fichas_familiares_tenant_ficha_parentesco (tenant_id, ficha_id, parentesco),
    KEY ix_fichas_familiares_tenant_ficha (tenant_id, ficha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- @down

DROP TABLE IF EXISTS fichas_familiares;
