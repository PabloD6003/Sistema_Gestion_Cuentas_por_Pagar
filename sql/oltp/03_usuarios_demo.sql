/* =====================================================================================
   Usuarios demo para pruebas locales.
   Requiere haber ejecutado 00_base.sql, 01_correcciones.sql y 02_lectura.sql.
   No usar estas credenciales en produccion.
   ===================================================================================== */

USE CuentasPorPagarDB;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

DECLARE @id_admin INT = NULL;
DECLARE @id_tesorero INT = NULL;
DECLARE @id_contador INT = NULL;
DECLARE @id_rol_admin INT;
DECLARE @id_rol_tesorero INT;
DECLARE @id_rol_contador INT;

SELECT @id_rol_admin = id_rol
FROM dbo.CP11_roles
WHERE nombre_rol = 'Administrador' AND estado = 1;

SELECT @id_rol_tesorero = id_rol
FROM dbo.CP11_roles
WHERE nombre_rol = 'Tesorero' AND estado = 1;

SELECT @id_rol_contador = id_rol
FROM dbo.CP11_roles
WHERE nombre_rol = 'Contador' AND estado = 1;

IF @id_rol_admin IS NULL OR @id_rol_tesorero IS NULL OR @id_rol_contador IS NULL
    THROW 51000, 'Faltan los roles base. Ejecute 00_base.sql antes de este script.', 1;

BEGIN TRY
    BEGIN TRANSACTION;

    SELECT @id_admin = id_usuario
    FROM dbo.CP12_usuarios WITH (UPDLOCK, HOLDLOCK)
    WHERE nombre_usuario = 'admin';

    IF @id_admin IS NULL
    BEGIN
        EXEC dbo.sp_usuario_crear
            @id_rol = @id_rol_admin,
            @nombre_usuario = 'admin',
            @nombre_completo = 'Administrador del sistema',
            @correo = 'admin@cxp.local',
            @contrasena = N'Admin1234',
            @id_usuario_admin = NULL,
            @id_usuario = @id_admin OUTPUT;

        PRINT 'Usuario admin creado.';
    END
    ELSE
        PRINT 'Usuario admin ya existe; se omite.';

    IF NOT EXISTS (SELECT 1 FROM dbo.CP12_usuarios WHERE nombre_usuario = 'tesorero')
    BEGIN
        EXEC dbo.sp_usuario_crear
            @id_rol = @id_rol_tesorero,
            @nombre_usuario = 'tesorero',
            @nombre_completo = 'Tesorero de prueba',
            @correo = 'tesorero@cxp.local',
            @contrasena = N'Tesorero123',
            @id_usuario_admin = @id_admin,
            @id_usuario = @id_tesorero OUTPUT;

        PRINT 'Usuario tesorero creado.';
    END
    ELSE
        PRINT 'Usuario tesorero ya existe; se omite.';

    IF NOT EXISTS (SELECT 1 FROM dbo.CP12_usuarios WHERE nombre_usuario = 'contador')
    BEGIN
        EXEC dbo.sp_usuario_crear
            @id_rol = @id_rol_contador,
            @nombre_usuario = 'contador',
            @nombre_completo = 'Contador de prueba',
            @correo = 'contador@cxp.local',
            @contrasena = N'Contador123',
            @id_usuario_admin = @id_admin,
            @id_usuario = @id_contador OUTPUT;

        PRINT 'Usuario contador creado.';
    END
    ELSE
        PRINT 'Usuario contador ya existe; se omite.';

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK TRANSACTION;

    THROW;
END CATCH;
GO

PRINT 'Usuarios demo listos.';
GO
