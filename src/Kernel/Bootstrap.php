<?php
declare(strict_types=1);

namespace DigiSecure\Kernel;

use DigiSecure\Infrastructure\Persistence\MigrationRunner;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Kernel\Container\Container;
use DigiSecure\Kernel\Error\ErrorHandler;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Middleware\AuditMiddleware;
use DigiSecure\Kernel\Middleware\AuthenticationMiddleware;
use DigiSecure\Kernel\Middleware\AuthorizationMiddleware;
use DigiSecure\Kernel\Middleware\CsrfMiddleware;
use DigiSecure\Kernel\Middleware\ErrorHandlerMiddleware;
use DigiSecure\Kernel\Middleware\Pipeline;
use DigiSecure\Kernel\Middleware\LayoutContextMiddleware;
use DigiSecure\Kernel\Middleware\SecureUrlMiddleware;
use DigiSecure\Kernel\Middleware\SecurityHeadersMiddleware;
use DigiSecure\Kernel\Middleware\SessionMiddleware;
use DigiSecure\Kernel\Middleware\TenantContextMiddleware;
use DigiSecure\Kernel\Routing\RouteDispatcher;
use DigiSecure\Kernel\Routing\Router;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Auth\Authenticator;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Security\Crypto\Hasher;
use DigiSecure\Security\Captcha\RecaptchaVerifier;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Security\Rbac\Authorizer;
use DigiSecure\Security\Session\SessionManager;
use DigiSecure\Security\Url\SecureUrlTokenService;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\Controller\DashboardController;
use DigiSecure\Security\Auth\Controller\LoginController;
use DigiSecure\Security\Auth\Controller\LogoutController;
use DigiSecure\Tenant\Domain\TenantContext;
use DigiSecure\User\Controller\ProfileController;
use DigiSecure\User\Controller\UserController;
use DigiSecure\Ficha\Controller\FichaController;
use DigiSecure\Expediente\Controller\ExpedienteController;
use DigiSecure\Reporting\Controller\KpiController;
use DigiSecure\Security\Audit\Controller\AuditoriaController;
use DigiSecure\Security\Rbac\Controller\RoleController;
use DigiSecure\Security\Lopdp\Controller\LopdpController;
use DigiSecure\Tenant\Controller\TenantController;
use DigiSecure\Notification\Controller\NotificacionController;
use DigiSecure\Expediente\Controller\DocumentoTipoController;
use DigiSecure\User\Controller\PasswordResetController;
use DigiSecure\Subscription\Controller\SuscripcionController;
use DigiSecure\Tenant\Controller\RegistroController;
use DigiSecure\Superadmin\Controller\SuperadminController;
use DigiSecure\Cliente\Controller\ClienteController;
use DigiSecure\Puesto\Controller\PuestoController;
use DigiSecure\Asignacion\Controller\AsignacionController;
use DigiSecure\Infrastructure\Import\Controller\ImportacionGemesegController;
use DigiSecure\Aspirante\Controller\AspiranteController;
use DigiSecure\Aspirante\Controller\AspiranteAdminController;
use DigiSecure\Aspirante\Controller\VerificacionController;

/**
 * Orquestador del arranque de la aplicación.
 *
 * Se invoca desde public/index.php. Su responsabilidad es:
 * 1. Cargar configuración desde .env
 * 2. Configurar el ErrorHandler global
 * 3. Construir el Container y registrar bindings
 * 4. Cargar las rutas
 * 5. Construir el pipeline de middlewares
 * 6. Procesar el request y enviar la response
 */
final class Bootstrap
{
    public function __construct(
        public readonly string $rootPath,
    ) {
    }

    public function run(): void
    {
        // 1. Configuración
        $config = $this->loadConfig();

        // 2. ErrorHandler global (antes de cualquier código que pueda fallar)
        $logPath = "{$this->rootPath}/{$config->string('log.path', 'var/logs/digisecure.log')}";
        $debug = $config->bool('app.debug', false);

        $errorHandler = new ErrorHandler($debug, $logPath);
        $errorHandler->setTemplatesPath("{$this->rootPath}/templates");
        $errorHandler->register();

        // 3. Container
        $container = new Container();
        $container->instance(Container::class, $container);
        $container->instance(Config::class, $config);
        $container->instance(ErrorHandler::class, $errorHandler);

        // 4. Persistencia (lazy: la conexión PDO solo se abre al primer uso)
        $container->singleton(
            PdoConnection::class,
            static fn(Container $c): PdoConnection => PdoConnection::fromConfig($c->get(Config::class)),
        );
        $container->singleton(
            MigrationRunner::class,
            fn(Container $c): MigrationRunner => new MigrationRunner(
                $c->get(PdoConnection::class),
                "{$this->rootPath}/migrations",
            ),
        );

        // 5. Seguridad base (Fase 0.5)
        $container->singleton(
            SessionManager::class,
            fn(Container $c): SessionManager => SessionManager::fromConfig(
                $c->get(Config::class),
                $this->rootPath,
            ),
        );
        $container->singleton(
            CsrfGuard::class,
            static fn(Container $c): CsrfGuard => new CsrfGuard($c->get(SessionManager::class)),
        );
        $container->singleton(
            Hasher::class,
            static fn(): Hasher => new Hasher(),
        );
        $container->singleton(
            Encryption::class,
            static fn(Container $c): Encryption => Encryption::fromHexKey(
                $c->get(Config::class)->string('encryption.key', '')
            ),
        );
        $container->singleton(
            SecureUrlTokenService::class,
            static fn(Container $c): SecureUrlTokenService => new SecureUrlTokenService(
                $c->get(Encryption::class),
                $c->get(Config::class)->int('security.secure_url_ttl', 900),
            ),
        );
        $container->singleton(
            RecaptchaVerifier::class,
            static fn(Container $c): RecaptchaVerifier => new RecaptchaVerifier(
                $c->get(Config::class)->string('recaptcha.site_key', ''),
                $c->get(Config::class)->string('recaptcha.secret', ''),
                $c->get(Config::class)->int('recaptcha.timeout_seconds', 5),
            ),
        );

        // 6. Multi-tenancy: TenantContext (request-scoped, singleton por request)
        $container->singleton(
            TenantContext::class,
            static fn(): TenantContext => new TenantContext(),
        );

        // 7. Auditoría
        $container->singleton(
            AuditLogger::class,
            static fn(Container $c): AuditLogger => new AuditLogger(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );

        // 8. RBAC
        $container->singleton(
            Authorizer::class,
            static fn(Container $c): Authorizer => new Authorizer(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );

        // 9. Authenticator
        $container->singleton(
            Authenticator::class,
            static fn(Container $c): Authenticator => new Authenticator(
                $c->get(PdoConnection::class),
                $c->get(SessionManager::class),
                $c->get(Authorizer::class),
                $c->get(AuditLogger::class),
                $c->get(TenantContext::class),
            ),
        );

        // 10. ViewRenderer (templates)
        $container->singleton(
            ViewRenderer::class,
            fn(): ViewRenderer => new ViewRenderer("{$this->rootPath}/templates"),
        );

        // 11. Módulo Ficha del Guardia
        $container->singleton(
            \DigiSecure\Ficha\Infrastructure\FichaRepository::class,
            static fn(Container $c): \DigiSecure\Ficha\Infrastructure\FichaRepository => new \DigiSecure\Ficha\Infrastructure\FichaRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Ficha\Application\CrearFichaService::class,
            static fn(Container $c) => new \DigiSecure\Ficha\Application\CrearFichaService(
                $c->get(\DigiSecure\Ficha\Infrastructure\FichaRepository::class),
                $c->get(PdoConnection::class),
                $c->get(Encryption::class),
                $c->get(AuditLogger::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Ficha\Application\ActualizarFichaService::class,
            static fn(Container $c) => new \DigiSecure\Ficha\Application\ActualizarFichaService(
                $c->get(\DigiSecure\Ficha\Infrastructure\FichaRepository::class),
                $c->get(PdoConnection::class),
                $c->get(Encryption::class),
                $c->get(AuditLogger::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Ficha\Application\CompletarFichaService::class,
            static fn(Container $c) => new \DigiSecure\Ficha\Application\CompletarFichaService(
                $c->get(\DigiSecure\Ficha\Infrastructure\FichaRepository::class),
                $c->get(AuditLogger::class),
                $c->get(\DigiSecure\Security\Lopdp\Application\ConsentManager::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Ficha\Application\EnviarRevisionFichaService::class,
            static fn(Container $c) => new \DigiSecure\Ficha\Application\EnviarRevisionFichaService(
                $c->get(\DigiSecure\Ficha\Infrastructure\FichaRepository::class),
                $c->get(AuditLogger::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Ficha\Application\EliminarFichaService::class,
            static fn(Container $c) => new \DigiSecure\Ficha\Application\EliminarFichaService(
                $c->get(\DigiSecure\Ficha\Infrastructure\FichaRepository::class),
                $c->get(AuditLogger::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Ficha\Application\RecuperarFichaService::class,
            static fn(Container $c) => new \DigiSecure\Ficha\Application\RecuperarFichaService(
                $c->get(\DigiSecure\Ficha\Infrastructure\FichaRepository::class),
                $c->get(AuditLogger::class),
            ),
        );

        // 12. Módulo Expediente Documental
        $container->singleton(
            \DigiSecure\Expediente\Infrastructure\ExpedienteRepository::class,
            static fn(Container $c) => new \DigiSecure\Expediente\Infrastructure\ExpedienteRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Expediente\Application\SubirDocumentoService::class,
            fn(Container $c) => new \DigiSecure\Expediente\Application\SubirDocumentoService(
                $c->get(\DigiSecure\Expediente\Infrastructure\ExpedienteRepository::class),
                $c->get(AuditLogger::class),
                "{$this->rootPath}/var/storage/documentos",
            ),
        );
        $container->singleton(
            \DigiSecure\Expediente\Application\RevisarDocumentoService::class,
            static fn(Container $c) => new \DigiSecure\Expediente\Application\RevisarDocumentoService(
                $c->get(\DigiSecure\Expediente\Infrastructure\ExpedienteRepository::class),
                $c->get(AuditLogger::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Expediente\Application\VerificarVencimientosService::class,
            static fn(Container $c) => new \DigiSecure\Expediente\Application\VerificarVencimientosService(
                $c->get(\DigiSecure\Expediente\Infrastructure\ExpedienteRepository::class),
                $c->get(AuditLogger::class),
            ),
        );
        // Fase 10: servicio de descarga de documentos
        $container->singleton(
            \DigiSecure\Expediente\Application\DescargarDocumentoService::class,
            fn(Container $c) => new \DigiSecure\Expediente\Application\DescargarDocumentoService(
                $c->get(\DigiSecure\Expediente\Infrastructure\ExpedienteRepository::class),
                $c->get(AuditLogger::class),
                "{$this->rootPath}/var/storage/documentos",
            ),
        );

        // 13. Módulo Reporting (KPIs / Dashboard Stats)
        $container->singleton(
            \DigiSecure\Reporting\Application\DashboardStatsService::class,
            static fn(Container $c) => new \DigiSecure\Reporting\Application\DashboardStatsService(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        // Fase 10: servicio de exportación de reportes CSV
        $container->singleton(
            \DigiSecure\Reporting\Application\ExportarReporteService::class,
            static fn(Container $c) => new \DigiSecure\Reporting\Application\ExportarReporteService(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
                $c->get(AuditLogger::class),
            ),
        );

        // 14. Módulo Notification (Fase 4)
        $container->singleton(
            \DigiSecure\Notification\Infrastructure\NotificacionRepository::class,
            static fn(Container $c) => new \DigiSecure\Notification\Infrastructure\NotificacionRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Notification\Application\EncolarNotificacionService::class,
            static fn(Container $c) => new \DigiSecure\Notification\Application\EncolarNotificacionService(
                $c->get(\DigiSecure\Notification\Infrastructure\NotificacionRepository::class),
                $c->get(AuditLogger::class),
            ),
        );
        // Centro de alertas del topbar (Fase 14): agrega pendientes accionables
        // sin persistir estado de lectura — es una vista de situacion en vivo.
        $container->singleton(
            \DigiSecure\Notification\Application\AlertasCentroService::class,
            static fn(Container $c) => new \DigiSecure\Notification\Application\AlertasCentroService(
                $c->get(PdoConnection::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Kernel\Middleware\AlertasLayoutMiddleware::class,
            static fn(Container $c) => new \DigiSecure\Kernel\Middleware\AlertasLayoutMiddleware(
                $c->get(\DigiSecure\Notification\Application\AlertasCentroService::class),
                $c->get(ViewRenderer::class),
            ),
        );
        // Fase 15: Flash messages (post-redirect-get) + toasts en el layout.
        $container->singleton(
            \DigiSecure\Kernel\Http\FlashBag::class,
            static fn(Container $c) => new \DigiSecure\Kernel\Http\FlashBag(
                $c->get(SessionManager::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Kernel\Middleware\FlashMiddleware::class,
            static fn(Container $c) => new \DigiSecure\Kernel\Middleware\FlashMiddleware(
                $c->get(\DigiSecure\Kernel\Http\FlashBag::class),
                $c->get(ViewRenderer::class),
            ),
        );
        // Sobrescribir VerificarVencimientosService para inyectar EncolarNotificacionService
        $container->singleton(
            \DigiSecure\Expediente\Application\VerificarVencimientosService::class,
            static fn(Container $c) => new \DigiSecure\Expediente\Application\VerificarVencimientosService(
                $c->get(\DigiSecure\Expediente\Infrastructure\ExpedienteRepository::class),
                $c->get(AuditLogger::class),
                $c->get(\DigiSecure\Notification\Application\EncolarNotificacionService::class),
                $c->get(Config::class)->string('whatsapp.phone_number_id', '') !== ''
                    && $c->get(Config::class)->string('whatsapp.access_token', '') !== '',
            ),
        );

        // 15. Módulo User Admin (Fase 4)
        $container->singleton(
            \DigiSecure\User\Infrastructure\UserRepository::class,
            static fn(Container $c) => new \DigiSecure\User\Infrastructure\UserRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\User\Application\AdministrarUsuariosService::class,
            static fn(Container $c) => new \DigiSecure\User\Application\AdministrarUsuariosService(
                $c->get(\DigiSecure\User\Infrastructure\UserRepository::class),
                $c->get(PdoConnection::class),
                $c->get(Hasher::class),
                $c->get(AuditLogger::class),
                $c->get(TenantContext::class),
            ),
        );

        // 16. Módulo Auditoría (Fase 4 — solo lectura)
        $container->singleton(
            \DigiSecure\Security\Audit\AuditoriaRepository::class,
            static fn(Container $c) => new \DigiSecure\Security\Audit\AuditoriaRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );

        // 17. Módulo Roles (Fase 4 lectura + Fase 9 CRUD)
        $container->singleton(
            \DigiSecure\Security\Rbac\RoleRepository::class,
            static fn(Container $c) => new \DigiSecure\Security\Rbac\RoleRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Security\Rbac\Application\GestionarRolesService::class,
            static fn(Container $c) => new \DigiSecure\Security\Rbac\Application\GestionarRolesService(
                $c->get(\DigiSecure\Security\Rbac\RoleRepository::class),
                $c->get(AuditLogger::class),
            ),
        );

        // 18. Módulo LOPDP (Fase 6)
        $container->singleton(
            \DigiSecure\Security\Lopdp\Infrastructure\SolicitudArcopRepository::class,
            static fn(Container $c) => new \DigiSecure\Security\Lopdp\Infrastructure\SolicitudArcopRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Security\Lopdp\Infrastructure\ConsentimientoRepository::class,
            static fn(Container $c) => new \DigiSecure\Security\Lopdp\Infrastructure\ConsentimientoRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Security\Lopdp\Application\RegistrarSolicitudArcopService::class,
            static fn(Container $c) => new \DigiSecure\Security\Lopdp\Application\RegistrarSolicitudArcopService(
                $c->get(\DigiSecure\Security\Lopdp\Infrastructure\SolicitudArcopRepository::class),
                $c->get(AuditLogger::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Security\Lopdp\Application\ResponderSolicitudArcopService::class,
            static fn(Container $c) => new \DigiSecure\Security\Lopdp\Application\ResponderSolicitudArcopService(
                $c->get(\DigiSecure\Security\Lopdp\Infrastructure\SolicitudArcopRepository::class),
                $c->get(AuditLogger::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Security\Lopdp\Application\ConsentManager::class,
            static fn(Container $c) => new \DigiSecure\Security\Lopdp\Application\ConsentManager(
                $c->get(\DigiSecure\Security\Lopdp\Infrastructure\ConsentimientoRepository::class),
                $c->get(AuditLogger::class),
            ),
        );

        // 19. PDFs del Guardia (Fase 6)
        $container->singleton(
            \DigiSecure\Ficha\Application\GenerarDeclaracionLealtadService::class,
            fn(Container $c) => new \DigiSecure\Ficha\Application\GenerarDeclaracionLealtadService(
                $c->get(\DigiSecure\Ficha\Infrastructure\FichaRepository::class),
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
                $c->get(Encryption::class),
                $c->get(AuditLogger::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Ficha\Application\GenerarFormatoHuellasService::class,
            fn(Container $c) => new \DigiSecure\Ficha\Application\GenerarFormatoHuellasService(
                $c->get(\DigiSecure\Ficha\Infrastructure\FichaRepository::class),
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
                $c->get(Encryption::class),
                $c->get(AuditLogger::class),
            ),
        );

        // 20. Módulo Tenant self-management (Fase 7)
        $container->singleton(
            \DigiSecure\Tenant\Infrastructure\TenantRepository::class,
            static fn(Container $c) => new \DigiSecure\Tenant\Infrastructure\TenantRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Tenant\Application\ActualizarTenantService::class,
            static fn(Container $c) => new \DigiSecure\Tenant\Application\ActualizarTenantService(
                $c->get(\DigiSecure\Tenant\Infrastructure\TenantRepository::class),
                $c->get(AuditLogger::class),
            ),
        );

        // 21. Módulo Notificaciones — Controller (Fase 8)
        // NotificacionRepository ya está en módulo 14; solo falta el controller DI
        // (autowiring lo resuelve, pero lo dejamos explícito para claridad)

        // 22. Módulo Documento Tipos — Catálogo configurable (Fase 8)
        $container->singleton(
            \DigiSecure\Expediente\Infrastructure\DocumentoTipoRepository::class,
            static fn(Container $c) => new \DigiSecure\Expediente\Infrastructure\DocumentoTipoRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Expediente\Application\ConfigurarDocumentoTipoService::class,
            static fn(Container $c) => new \DigiSecure\Expediente\Application\ConfigurarDocumentoTipoService(
                $c->get(\DigiSecure\Expediente\Infrastructure\DocumentoTipoRepository::class),
                $c->get(AuditLogger::class),
            ),
        );

        // 23. Módulo Password Reset (Fase 10 — flujo público de recuperación)
        $container->singleton(
            \DigiSecure\User\Application\RequestPasswordResetService::class,
            static fn(Container $c) => new \DigiSecure\User\Application\RequestPasswordResetService(
                $c->get(PdoConnection::class),
                $c->get(\DigiSecure\Notification\Application\EncolarNotificacionService::class),
                $c->get(AuditLogger::class),
                $c->get(TenantContext::class),
                $c->get(Config::class),
            ),
        );
        $container->singleton(
            \DigiSecure\User\Application\ResetPasswordService::class,
            static fn(Container $c) => new \DigiSecure\User\Application\ResetPasswordService(
                $c->get(PdoConnection::class),
                $c->get(Hasher::class),
                $c->get(AuditLogger::class),
                $c->get(TenantContext::class),
            ),
        );

        // 24. Módulo Subscription / Planes (Fase 11)
        $container->singleton(
            \DigiSecure\Subscription\Infrastructure\PlanRepository::class,
            static fn(Container $c) => new \DigiSecure\Subscription\Infrastructure\PlanRepository(
                $c->get(PdoConnection::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Subscription\Infrastructure\SuscripcionCicloRepository::class,
            static fn(Container $c) => new \DigiSecure\Subscription\Infrastructure\SuscripcionCicloRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Subscription\Application\CalcularUsoService::class,
            static fn(Container $c) => new \DigiSecure\Subscription\Application\CalcularUsoService(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Subscription\Application\VerificarLimiteService::class,
            static fn(Container $c) => new \DigiSecure\Subscription\Application\VerificarLimiteService(
                $c->get(\DigiSecure\Tenant\Infrastructure\TenantRepository::class),
                $c->get(\DigiSecure\Subscription\Application\CalcularUsoService::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Subscription\Application\ConsultarSuscripcionService::class,
            static fn(Container $c) => new \DigiSecure\Subscription\Application\ConsultarSuscripcionService(
                $c->get(\DigiSecure\Tenant\Infrastructure\TenantRepository::class),
                $c->get(\DigiSecure\Subscription\Infrastructure\PlanRepository::class),
                $c->get(\DigiSecure\Subscription\Infrastructure\SuscripcionCicloRepository::class),
                $c->get(\DigiSecure\Subscription\Application\CalcularUsoService::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Subscription\Application\RegistrarCicloService::class,
            static fn(Container $c) => new \DigiSecure\Subscription\Application\RegistrarCicloService(
                $c->get(PdoConnection::class),
                $c->get(\DigiSecure\Subscription\Infrastructure\PlanRepository::class),
                $c->get(\DigiSecure\Subscription\Infrastructure\SuscripcionCicloRepository::class),
                $c->get(TenantContext::class),
                $c->get(AuditLogger::class),
            ),
        );

        // 25. Módulo Registro público de tenants (Fase 12)
        $container->singleton(
            \DigiSecure\Tenant\Infrastructure\RegistroEmpresaRepository::class,
            static fn(Container $c) => new \DigiSecure\Tenant\Infrastructure\RegistroEmpresaRepository(
                $c->get(PdoConnection::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Tenant\Application\RegistrarTenantService::class,
            static fn(Container $c) => new \DigiSecure\Tenant\Application\RegistrarTenantService(
                $c->get(PdoConnection::class),
                $c->get(Hasher::class),
                $c->get(\DigiSecure\Subscription\Infrastructure\PlanRepository::class),
                $c->get(\DigiSecure\Subscription\Infrastructure\SuscripcionCicloRepository::class),
                $c->get(\DigiSecure\Tenant\Infrastructure\RegistroEmpresaRepository::class),
                $c->get(\DigiSecure\Notification\Application\EncolarNotificacionService::class),
                $c->get(TenantContext::class),
                $c->get(Config::class),
            ),
        );
        $container->singleton(
            \DigiSecure\Tenant\Application\ActivarTenantService::class,
            static fn(Container $c) => new \DigiSecure\Tenant\Application\ActivarTenantService(
                $c->get(PdoConnection::class),
                $c->get(\DigiSecure\Tenant\Infrastructure\RegistroEmpresaRepository::class),
            ),
        );
        $container->singleton(
            RegistroController::class,
            static fn(Container $c) => new RegistroController(
                $c->get(ViewRenderer::class),
                $c->get(\DigiSecure\Tenant\Application\RegistrarTenantService::class),
                $c->get(\DigiSecure\Tenant\Application\ActivarTenantService::class),
                $c->get(\DigiSecure\Subscription\Infrastructure\PlanRepository::class),
            ),
        );

        // 26. Módulo Superadmin (Fase 13)
        $container->singleton(
            \DigiSecure\Superadmin\Infrastructure\SuperadminRepository::class,
            static fn(Container $c) => new \DigiSecure\Superadmin\Infrastructure\SuperadminRepository(
                $c->get(PdoConnection::class),
            ),
        );
        $container->singleton(
            SuperadminController::class,
            static fn(Container $c) => new SuperadminController(
                $c->get(ViewRenderer::class),
                $c->get(\DigiSecure\Superadmin\Infrastructure\SuperadminRepository::class),
                $c->get(\DigiSecure\Subscription\Infrastructure\PlanRepository::class),
                $c->get(PdoConnection::class),
                $c->get(AuditLogger::class),
                $c->get(TenantContext::class),
                $c->get(\DigiSecure\Kernel\Http\FlashBag::class),
            ),
        );

        // 27. Importaciones controladas
        $container->singleton(
            \DigiSecure\Infrastructure\Import\XlsxReader::class,
            static fn(): \DigiSecure\Infrastructure\Import\XlsxReader => new \DigiSecure\Infrastructure\Import\XlsxReader(),
        );
        $container->singleton(
            \DigiSecure\Infrastructure\Import\GemesegExcelImportService::class,
            fn(Container $c): \DigiSecure\Infrastructure\Import\GemesegExcelImportService => new \DigiSecure\Infrastructure\Import\GemesegExcelImportService(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
                $c->get(Encryption::class),
                $c->get(AuditLogger::class),
                $c->get(\DigiSecure\Infrastructure\Import\XlsxReader::class),
                "{$this->rootPath}/var/imports/gemeseg",
            ),
        );
        $container->singleton(
            \DigiSecure\Infrastructure\Import\GemesegStagingProcessorService::class,
            static fn(Container $c): \DigiSecure\Infrastructure\Import\GemesegStagingProcessorService => new \DigiSecure\Infrastructure\Import\GemesegStagingProcessorService(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
                $c->get(Encryption::class),
                $c->get(AuditLogger::class),
            ),
        );
        $container->singleton(
            ImportacionGemesegController::class,
            static fn(Container $c): ImportacionGemesegController => new ImportacionGemesegController(
                $c->get(ViewRenderer::class),
                $c->get(\DigiSecure\Infrastructure\Import\GemesegExcelImportService::class),
                $c->get(\DigiSecure\Infrastructure\Import\GemesegStagingProcessorService::class),
                $c->get(\DigiSecure\Kernel\Http\FlashBag::class),
            ),
        );

        // 28. Modulo Aspirantes (registro publico desde el login)
        // Se registra explicitamente para inyectar la lista negra de dominios
        // de email desde configuracion (autowiring no resuelve el array).
        $container->singleton(
            \DigiSecure\Aspirante\Application\RegistrarAspiranteService::class,
            static fn(Container $c) => new \DigiSecure\Aspirante\Application\RegistrarAspiranteService(
                $c->get(\DigiSecure\Aspirante\Infrastructure\AspiranteRepository::class),
                $c->get(Hasher::class),
                array_values(array_filter(
                    array_map(
                        static fn(string $d): string => strtolower(trim($d)),
                        explode(',', $c->get(Config::class)->string('registro.email_blacklist', '')),
                    ),
                    static fn(string $d): bool => $d !== '',
                )),
            ),
        );
        $container->singleton(
            \DigiSecure\Aspirante\Application\GestionarAspirantesService::class,
            static fn(Container $c) => new \DigiSecure\Aspirante\Application\GestionarAspirantesService(
                $c->get(\DigiSecure\Aspirante\Infrastructure\AspiranteRepository::class),
                $c->get(PdoConnection::class),
                $c->get(AuditLogger::class),
                $c->get(Config::class)->string('registro.aprobacion_rol_codigo', ''),
            ),
        );

        // 14. Router con rutas registradas
        $router = new Router();
        $this->registerRoutes($router, $config);
        $container->instance(Router::class, $router);

        // 7. Detectar base path y crear Request
        \DigiSecure\Kernel\Http\BasePath::detect();
        $request = Request::fromGlobals();

        // 8. Pipeline de middlewares globales
        // ORDEN CRÍTICO:
        //  - SecurityHeaders envuelve todo: incluso 404/500 deben llevar headers de seguridad.
        //  - ErrorHandler captura excepciones del resto del pipeline (incluida CsrfException).
        //  - Session debe iniciarse antes que CSRF (CSRF lee/escribe en sesión).
        //  - CSRF debe ejecutarse antes del handler para abortar requests inválidos.
        //  - Authentication carga el usuario de sesión y protege rutas.
        //  - TenantContext establece el tenant_id para BaseRepository.
        //  - RouteResolver resuelve la ruta y expone _route_permission ANTES de Authorization.
        //  - SecureUrl tokeniza links sensibles y decodifica params tokenizados.
        //  - Authorization verifica permisos de ruta.
        //  - Audit registra operaciones de escritura.
        $globalMiddlewares = [
            SecurityHeadersMiddleware::class,
            ErrorHandlerMiddleware::class,
            SessionMiddleware::class,
            CsrfMiddleware::class,
            AuthenticationMiddleware::class,
            LayoutContextMiddleware::class,
            TenantContextMiddleware::class,
            \DigiSecure\Kernel\Middleware\RouteResolverMiddleware::class,
            SecureUrlMiddleware::class,
            AuthorizationMiddleware::class,
            \DigiSecure\Kernel\Middleware\AlertasLayoutMiddleware::class,
            \DigiSecure\Kernel\Middleware\FlashMiddleware::class,
            AuditMiddleware::class,
        ];

        $dispatcher = new RouteDispatcher($router, $container);
        $pipeline = new Pipeline($container, $globalMiddlewares, $dispatcher);

        // 7. Procesar y enviar
        $response = $pipeline->handle($request);
        $response->send();
    }

    private function loadConfig(): Config
    {
        $envFile = "{$this->rootPath}/.env";
        $envVars = EnvLoader::load($envFile);

        // Mapear variables planas del .env a un árbol jerárquico
        $values = [
            'app' => [
                'name' => $envVars['APP_NAME'] ?? 'DigiSecure',
                'env' => $envVars['APP_ENV'] ?? 'production',
                'debug' => $envVars['APP_DEBUG'] ?? 'false',
                'url' => $envVars['APP_URL'] ?? '',
                'timezone' => $envVars['APP_TIMEZONE'] ?? 'America/Guayaquil',
                'locale' => $envVars['APP_LOCALE'] ?? 'es_EC',
                'key' => $envVars['APP_KEY'] ?? '',
            ],
            'db' => [
                'host' => $envVars['DB_HOST'] ?? '127.0.0.1',
                'port' => $envVars['DB_PORT'] ?? '3306',
                'database' => $envVars['DB_DATABASE'] ?? 'digisecure',
                'username' => $envVars['DB_USERNAME'] ?? '',
                'password' => $envVars['DB_PASSWORD'] ?? '',
                'charset' => $envVars['DB_CHARSET'] ?? 'utf8mb4',
                'collation' => $envVars['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
            ],
            'session' => [
                'name' => $envVars['SESSION_NAME'] ?? 'DIGISECURE_SID',
                'lifetime' => $envVars['SESSION_LIFETIME_MINUTES'] ?? '120',
                'cookie_secure' => $envVars['SESSION_COOKIE_SECURE'] ?? 'false',
                'cookie_httponly' => $envVars['SESSION_COOKIE_HTTPONLY'] ?? 'true',
                'cookie_samesite' => $envVars['SESSION_COOKIE_SAMESITE'] ?? 'Strict',
                'save_path' => $envVars['SESSION_SAVE_PATH'] ?? 'var/sessions',
            ],
            'encryption' => [
                'key' => $envVars['ENCRYPTION_KEY'] ?? '',
            ],
            'security' => [
                'secure_url_ttl' => $envVars['SECURE_URL_TTL_SECONDS'] ?? '900',
            ],
            'recaptcha' => [
                'site_key' => $envVars['RECAPTCHA_SITE_KEY'] ?? '',
                'secret' => $envVars['RECAPTCHA_SECRET_KEY'] ?? '',
                'timeout_seconds' => $envVars['RECAPTCHA_TIMEOUT_SECONDS'] ?? '5',
            ],
            'registro' => [
                'email_blacklist' => $envVars['REGISTRO_EMAIL_BLACKLIST'] ?? '',
                'aprobacion_rol_codigo' => $envVars['REGISTRO_APROBACION_ROL'] ?? '',
            ],
            'mail' => [
                'driver' => $envVars['MAIL_DRIVER'] ?? 'smtp',
                'host' => $envVars['MAIL_HOST'] ?? '',
                'port' => $envVars['MAIL_PORT'] ?? '587',
                'username' => $envVars['MAIL_USERNAME'] ?? '',
                'password' => $envVars['MAIL_PASSWORD'] ?? '',
                'encryption' => $envVars['MAIL_ENCRYPTION'] ?? 'tls',
                'from_address' => $envVars['MAIL_FROM_ADDRESS'] ?? '',
                'from_name' => $envVars['MAIL_FROM_NAME'] ?? '',
            ],
            'whatsapp' => [
                'driver' => $envVars['WHATSAPP_DRIVER'] ?? 'meta_cloud',
                'phone_number_id' => $envVars['WHATSAPP_PHONE_NUMBER_ID'] ?? '',
                'business_account_id' => $envVars['WHATSAPP_BUSINESS_ACCOUNT_ID'] ?? '',
                'access_token' => $envVars['WHATSAPP_ACCESS_TOKEN'] ?? '',
                'verify_token' => $envVars['WHATSAPP_VERIFY_TOKEN'] ?? '',
                'timeout_seconds' => $envVars['WHATSAPP_TIMEOUT_SECONDS'] ?? '15',
            ],
            'log' => [
                'path' => $envVars['LOG_PATH'] ?? 'var/logs/digisecure.log',
                'level' => $envVars['LOG_LEVEL'] ?? 'info',
            ],
        ];

        // Configurar timezone PHP
        date_default_timezone_set($values['app']['timezone']);

        return new Config($values);
    }

    private function registerRoutes(Router $router, Config $config): void
    {
        // Las rutas se moverán a config/routes.php cuando crezcan.

        // --- Rutas públicas ---
        $router->get('/', static fn(Authenticator $auth) => $auth->isAuthenticated()
            ? \DigiSecure\Kernel\Http\Response::redirect('/dashboard')
            : \DigiSecure\Kernel\Http\Response::redirect('/login'),
            name: 'home',
        );

        $router->get('/login', [LoginController::class, 'showForm'], name: 'login');
        $router->post('/login', [LoginController::class, 'processLogin'], name: 'login.process');

        // Fase 10: recuperación de contraseña (rutas públicas)
        $router->get('/password/forgot', [PasswordResetController::class, 'showForgotForm'], name: 'password.forgot');
        $router->post('/password/forgot', [PasswordResetController::class, 'processForgot'], name: 'password.forgot.process');
        $router->get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'], name: 'password.reset');
        $router->post('/password/reset/{token}', [PasswordResetController::class, 'processReset'], name: 'password.reset.process');

        // --- Rutas protegidas ---
        $router->post('/logout', [LogoutController::class, 'logout'], name: 'logout');
        $router->get('/dashboard', [DashboardController::class, 'index'], name: 'dashboard');

        // Perfil de usuario
        $router->get('/perfil', [ProfileController::class, 'show'], name: 'perfil');
        $router->get('/perfil/password', [ProfileController::class, 'showPasswordForm'], name: 'perfil.password');
        $router->post('/perfil/password', [ProfileController::class, 'changePassword'], name: 'perfil.password.change');

        // Fichas de guardia (Fase 1)
        $router->get('/guardias', [FichaController::class, 'index'], name: 'fichas.index', permission: 'guardias.ver');
        $router->get('/guardias/{id}/fichas', [FichaController::class, 'historial'], name: 'fichas.historial', permission: 'guardias.ver');
        $router->get('/guardias/{id}/fichas/exportar', [FichaController::class, 'exportarHistorial'], name: 'fichas.historial.export', permission: 'guardias.ver');
        $router->get('/guardias/crear', [FichaController::class, 'create'], name: 'fichas.create', permission: 'guardias.crear');
        $router->get('/guardias/prefill/cedula', [FichaController::class, 'prefillByCedula'], name: 'fichas.prefill.cedula', permission: 'guardias.crear');
        $router->post('/guardias/crear', [FichaController::class, 'store'], name: 'fichas.store', permission: 'guardias.crear');
        $router->get('/guardias/{id}', [FichaController::class, 'show'], name: 'fichas.show', permission: 'guardias.ver');
        $router->get('/guardias/{id}/editar', [FichaController::class, 'edit'], name: 'fichas.edit', permission: 'guardias.editar');
        $router->post('/guardias/{id}/editar', [FichaController::class, 'update'], name: 'fichas.update', permission: 'guardias.editar');
        $router->post('/guardias/{id}/revision', [FichaController::class, 'enviarRevision'], name: 'fichas.revision', permission: 'guardias.editar');
        $router->post('/guardias/{id}/completar', [FichaController::class, 'completar'], name: 'fichas.completar', permission: 'guardias.editar');
        $router->post('/guardias/{id}/eliminar', [FichaController::class, 'destroy'], name: 'fichas.destroy', permission: 'guardias.eliminar');
        $router->post('/guardias/{id}/recuperar', [FichaController::class, 'recover'], name: 'fichas.recover', permission: 'guardias.eliminar');

        // PDFs de guardia (Fase 6)
        $router->get('/guardias/{id}/declaracion-lealtad.pdf', [FichaController::class, 'descargarDeclaracionLealtad'], name: 'fichas.pdf.declaracion', permission: 'guardias.ver');
        $router->get('/guardias/{id}/formato-huellas.pdf', [FichaController::class, 'descargarFormatoHuellas'], name: 'fichas.pdf.huellas', permission: 'guardias.ver');

        // Expediente documental (Fase 2)
        $router->get('/expedientes', [ExpedienteController::class, 'index'], name: 'expedientes.index', permission: 'expedientes.ver');
        $router->get('/expedientes/pendientes', [ExpedienteController::class, 'pendientes'], name: 'expedientes.pendientes', permission: 'expedientes.validar');
        $router->get('/guardias/{fichaId}/expediente', [ExpedienteController::class, 'dashboard'], name: 'expedientes.dashboard', permission: 'expedientes.ver');
        $router->get('/expedientes/{id}/documento/{tipoId}', [ExpedienteController::class, 'showUploadForm'], name: 'expedientes.documento.upload.form', permission: 'expedientes.cargar');
        $router->post('/expedientes/{id}/documento/{tipoId}', [ExpedienteController::class, 'upload'], name: 'expedientes.documento.upload', permission: 'expedientes.cargar');
        // Fase 10: descarga de archivo físico (debe ir antes de /documento/{docId} para no conflictuar)
        $router->get('/expedientes/documento/{docId}/descargar', [ExpedienteController::class, 'descargarDocumento'], name: 'expedientes.documento.descargar', permission: 'expedientes.ver');
        $router->get('/expedientes/documento/{docId}', [ExpedienteController::class, 'showDocumento'], name: 'expedientes.documento.show', permission: 'expedientes.ver');
        $router->post('/expedientes/documento/{docId}/revisar', [ExpedienteController::class, 'revisar'], name: 'expedientes.documento.revisar', permission: 'expedientes.validar');

        // Reportes y KPIs (Fase 3)
        $router->get('/reportes/kpis', [KpiController::class, 'dashboard'], name: 'reportes.kpis', permission: 'reportes.ver');
        // Fase 10: exportaciones CSV (rutas específicas antes de la base)
        $router->get('/reportes/exportar/fichas', [KpiController::class, 'exportarFichas'], name: 'reportes.exportar.fichas', permission: 'reportes.exportar');
        $router->get('/reportes/exportar/expedientes', [KpiController::class, 'exportarExpedientes'], name: 'reportes.exportar.expedientes', permission: 'reportes.exportar');
        $router->get('/reportes/exportar/vencimientos', [KpiController::class, 'exportarVencimientos'], name: 'reportes.exportar.vencimientos', permission: 'reportes.exportar');
        $router->get('/reportes/exportar', [KpiController::class, 'exportar'], name: 'reportes.exportar', permission: 'reportes.exportar');

        // Importaciones controladas
        $router->get('/importaciones/gemeseg', [ImportacionGemesegController::class, 'index'], name: 'importaciones.gemeseg', permission: 'importaciones.gemeseg');
        $router->post('/importaciones/gemeseg/analizar', [ImportacionGemesegController::class, 'analyze'], name: 'importaciones.gemeseg.analyze', permission: 'importaciones.gemeseg');
        $router->post('/importaciones/gemeseg/aplicar', [ImportacionGemesegController::class, 'apply'], name: 'importaciones.gemeseg.apply', permission: 'importaciones.gemeseg');
        $router->post('/importaciones/gemeseg/procesar', [ImportacionGemesegController::class, 'process'], name: 'importaciones.gemeseg.process', permission: 'importaciones.gemeseg');

        // Administración de usuarios (Fase 4)
        $router->get('/usuarios', [UserController::class, 'index'], name: 'usuarios.index', permission: 'usuarios.ver');
        $router->get('/usuarios/crear', [UserController::class, 'create'], name: 'usuarios.create', permission: 'usuarios.crear');
        $router->post('/usuarios/crear', [UserController::class, 'store'], name: 'usuarios.store', permission: 'usuarios.crear');
        $router->get('/usuarios/{id}/editar', [UserController::class, 'edit'], name: 'usuarios.edit', permission: 'usuarios.editar');
        $router->post('/usuarios/{id}/editar', [UserController::class, 'update'], name: 'usuarios.update', permission: 'usuarios.editar');
        $router->post('/usuarios/{id}/estado', [UserController::class, 'cambiarEstado'], name: 'usuarios.estado', permission: 'usuarios.bloquear');
        $router->post('/usuarios/{id}/password', [UserController::class, 'resetPassword'], name: 'usuarios.password', permission: 'usuarios.editar');

        // Auditoría (Fase 4 read + Fase 9 export)
        $router->get('/auditoria', [AuditoriaController::class, 'index'], name: 'auditoria.index', permission: 'auditoria.ver');
        $router->get('/auditoria/exportar', [AuditoriaController::class, 'exportar'], name: 'auditoria.exportar', permission: 'auditoria.exportar');

        // Roles y permisos (Fase 4 read + Fase 9 CRUD)
        $router->get('/roles', [RoleController::class, 'index'], name: 'roles.index', permission: 'roles.ver');
        $router->get('/roles/crear', [RoleController::class, 'createForm'], name: 'roles.create', permission: 'roles.crear');
        $router->post('/roles/crear', [RoleController::class, 'store'], name: 'roles.store', permission: 'roles.crear');
        $router->get('/roles/{id}', [RoleController::class, 'show'], name: 'roles.show', permission: 'roles.ver');
        $router->get('/roles/{id}/editar', [RoleController::class, 'editForm'], name: 'roles.edit', permission: 'roles.editar');
        $router->post('/roles/{id}/editar', [RoleController::class, 'update'], name: 'roles.update', permission: 'roles.editar');
        $router->post('/roles/{id}/eliminar', [RoleController::class, 'destroy'], name: 'roles.destroy', permission: 'roles.eliminar');

        // Empresa / Tenant (Fase 7)
        $router->get('/empresa', [TenantController::class, 'show'], name: 'tenant.show', permission: 'tenants.ver');
        $router->get('/empresa/editar', [TenantController::class, 'edit'], name: 'tenant.edit', permission: 'tenants.editar');
        $router->post('/empresa/editar', [TenantController::class, 'update'], name: 'tenant.update', permission: 'tenants.editar');

        // Suscripción y planes (Fase 11)
        $router->get('/suscripcion', [SuscripcionController::class, 'show'], name: 'suscripcion.show', permission: 'suscripcion.ver');
        $router->get('/suscripcion/ciclo/nuevo', [SuscripcionController::class, 'nuevoCicloForm'], name: 'suscripcion.ciclo.form', permission: 'suscripcion.gestionar');
        $router->post('/suscripcion/ciclo/nuevo', [SuscripcionController::class, 'nuevoCiclo'], name: 'suscripcion.ciclo.store', permission: 'suscripcion.gestionar');

        // Notificaciones (Fase 8)
        $router->get('/notificaciones', [NotificacionController::class, 'index'], name: 'notificaciones.index', permission: 'notificaciones.ver');
        $router->get('/notificaciones/{id}', [NotificacionController::class, 'show'], name: 'notificaciones.show', permission: 'notificaciones.ver');
        $router->post('/notificaciones/{id}/retry', [NotificacionController::class, 'retry'], name: 'notificaciones.retry', permission: 'notificaciones.gestionar');

        // Configuración — Tipos de documento (Fase 8)
        $router->get('/configuracion/documentos', [DocumentoTipoController::class, 'index'], name: 'doctipo.index', permission: 'configuracion.documentos');
        $router->get('/configuracion/documentos/{codigo}', [DocumentoTipoController::class, 'edit'], name: 'doctipo.edit', permission: 'configuracion.documentos');
        $router->post('/configuracion/documentos/{codigo}', [DocumentoTipoController::class, 'update'], name: 'doctipo.update', permission: 'configuracion.documentos');
        $router->post('/configuracion/documentos/{codigo}/toggle', [DocumentoTipoController::class, 'toggle'], name: 'doctipo.toggle', permission: 'configuracion.documentos');

        // LOPDP — Protección de datos personales (Fase 6)
        $router->get('/lopdp', [LopdpController::class, 'dashboard'], name: 'lopdp.index', permission: 'lopdp.gestionar');
        $router->get('/lopdp/solicitudes', [LopdpController::class, 'listarSolicitudes'], name: 'lopdp.solicitudes.index', permission: 'lopdp.gestionar');
        $router->get('/lopdp/solicitudes/crear', [LopdpController::class, 'crearSolicitudForm'], name: 'lopdp.solicitudes.create', permission: 'lopdp.gestionar');
        $router->post('/lopdp/solicitudes/crear', [LopdpController::class, 'crearSolicitud'], name: 'lopdp.solicitudes.store', permission: 'lopdp.gestionar');
        $router->post('/lopdp/solicitudes/titular/buscar', [LopdpController::class, 'buscarTitular'], name: 'lopdp.solicitudes.titular.buscar', permission: 'lopdp.gestionar');
        $router->get('/lopdp/solicitudes/{id}', [LopdpController::class, 'verSolicitud'], name: 'lopdp.solicitudes.show', permission: 'lopdp.gestionar');
        $router->post('/lopdp/solicitudes/{id}/responder', [LopdpController::class, 'responderSolicitud'], name: 'lopdp.solicitudes.responder', permission: 'lopdp.gestionar');
        $router->get('/lopdp/consentimientos', [LopdpController::class, 'listarConsentimientos'], name: 'lopdp.consentimientos.index', permission: 'lopdp.gestionar');
        $router->get('/lopdp/consentimientos/PoliticaPrivacidad_TDP.pdf', [LopdpController::class, 'politicaPdf'], name: 'lopdp.consentimientos.politica');
        $router->get('/lopdp/consentimientos/{id}', [LopdpController::class, 'verConsentimiento'], name: 'lopdp.consentimientos.show', permission: 'lopdp.gestionar');
        $router->post('/lopdp/consentimientos/{id}/revocar', [LopdpController::class, 'revocarConsentimiento'], name: 'lopdp.consentimientos.revocar', permission: 'lopdp.gestionar');

        // Clientes (Fase 16)
        $router->get('/clientes', [ClienteController::class, 'index'], name: 'clientes.index', permission: 'clientes.ver');
        $router->get('/clientes/crear', [ClienteController::class, 'create'], name: 'clientes.create', permission: 'clientes.crear');
        $router->post('/clientes/crear', [ClienteController::class, 'store'], name: 'clientes.store', permission: 'clientes.crear');
        $router->get('/clientes/{id}', [ClienteController::class, 'show'], name: 'clientes.show', permission: 'clientes.ver');
        $router->get('/clientes/{id}/editar', [ClienteController::class, 'edit'], name: 'clientes.edit', permission: 'clientes.editar');
        $router->post('/clientes/{id}/editar', [ClienteController::class, 'update'], name: 'clientes.update', permission: 'clientes.editar');
        $router->post('/clientes/{id}/estado', [ClienteController::class, 'cambiarEstado'], name: 'clientes.estado', permission: 'clientes.archivar');

        // Puestos / Sitios (Fase 17)
        $router->get('/puestos', [PuestoController::class, 'index'], name: 'puestos.index', permission: 'puestos.ver');
        $router->get('/puestos/crear', [PuestoController::class, 'create'], name: 'puestos.create', permission: 'puestos.crear');
        $router->post('/puestos/crear', [PuestoController::class, 'store'], name: 'puestos.store', permission: 'puestos.crear');
        $router->get('/puestos/{id}', [PuestoController::class, 'show'], name: 'puestos.show', permission: 'puestos.ver');
        $router->get('/puestos/{id}/editar', [PuestoController::class, 'edit'], name: 'puestos.edit', permission: 'puestos.editar');
        $router->post('/puestos/{id}/editar', [PuestoController::class, 'update'], name: 'puestos.update', permission: 'puestos.editar');
        $router->post('/puestos/{id}/estado', [PuestoController::class, 'cambiarEstado'], name: 'puestos.estado', permission: 'puestos.archivar');

        // Asignaciones guardia <-> puesto (Fase 18)
        $router->get('/asignaciones', [AsignacionController::class, 'index'], name: 'asignaciones.index', permission: 'asignaciones.ver');
        $router->get('/asignaciones/crear', [AsignacionController::class, 'create'], name: 'asignaciones.create', permission: 'asignaciones.crear');
        $router->post('/asignaciones/crear', [AsignacionController::class, 'store'], name: 'asignaciones.store', permission: 'asignaciones.crear');
        $router->get('/asignaciones/{id}', [AsignacionController::class, 'show'], name: 'asignaciones.show', permission: 'asignaciones.ver');
        $router->get('/asignaciones/{id}/editar', [AsignacionController::class, 'edit'], name: 'asignaciones.edit', permission: 'asignaciones.editar');
        $router->post('/asignaciones/{id}/editar', [AsignacionController::class, 'update'], name: 'asignaciones.update', permission: 'asignaciones.editar');
        $router->post('/asignaciones/{id}/cerrar', [AsignacionController::class, 'cerrar'], name: 'asignaciones.cerrar', permission: 'asignaciones.finalizar');

        // Panel Superadmin (Fase 13)
        $router->get('/admin', [SuperadminController::class, 'dashboard'], name: 'admin.dashboard', permission: 'superadmin.panel');
        $router->get('/admin/planes', [SuperadminController::class, 'planes'], name: 'admin.planes', permission: 'superadmin.planes.ver');
        $router->get('/admin/planes/crear', [SuperadminController::class, 'planCrearForm'], name: 'admin.planes.create', permission: 'superadmin.planes.gestionar');
        $router->post('/admin/planes/crear', [SuperadminController::class, 'planCrear'], name: 'admin.planes.store', permission: 'superadmin.planes.gestionar');
        $router->get('/admin/planes/{codigo}/editar', [SuperadminController::class, 'planEditarForm'], name: 'admin.planes.edit', permission: 'superadmin.planes.gestionar');
        $router->post('/admin/planes/{codigo}/editar', [SuperadminController::class, 'planActualizar'], name: 'admin.planes.update', permission: 'superadmin.planes.gestionar');
        $router->post('/admin/planes/{codigo}/estado', [SuperadminController::class, 'planCambiarEstado'], name: 'admin.planes.estado', permission: 'superadmin.planes.gestionar');
        $router->get('/admin/tenants', [SuperadminController::class, 'tenants'], name: 'admin.tenants', permission: 'superadmin.tenants.ver');
        $router->get('/admin/tenants/crear', [SuperadminController::class, 'tenantCrearForm'], name: 'admin.tenants.create', permission: 'superadmin.tenants.gestionar');
        $router->post('/admin/tenants/crear', [SuperadminController::class, 'tenantCrear'], name: 'admin.tenants.store', permission: 'superadmin.tenants.gestionar');
        $router->get('/admin/tenants/{id}', [SuperadminController::class, 'tenantDetalle'], name: 'admin.tenants.detalle', permission: 'superadmin.tenants.ver');
        $router->get('/admin/tenants/{id}/editar', [SuperadminController::class, 'tenantEditarForm'], name: 'admin.tenants.edit', permission: 'superadmin.tenants.gestionar');
        $router->post('/admin/tenants/{id}/editar', [SuperadminController::class, 'tenantActualizar'], name: 'admin.tenants.update', permission: 'superadmin.tenants.gestionar');
        $router->post('/admin/tenants/{id}/estado', [SuperadminController::class, 'cambiarEstado'], name: 'admin.tenants.estado', permission: 'superadmin.tenants.gestionar');
        $router->post('/admin/tenants/{id}/plan', [SuperadminController::class, 'cambiarPlan'], name: 'admin.tenants.plan', permission: 'superadmin.tenants.gestionar');
        $router->post('/admin/tenants/{id}/usuarios/asociar', [SuperadminController::class, 'asociarUsuarioTenant'], name: 'admin.tenants.usuarios.asociar', permission: 'superadmin.tenants.gestionar');
        $router->get('/admin/registros', [SuperadminController::class, 'registros'], name: 'admin.registros', permission: 'superadmin.registros.ver');

        // Registro público y activación (Fase 12) — rutas públicas, sin permission
        $router->get('/registro', [RegistroController::class, 'showForm'], name: 'registro.form');
        $router->post('/registro', [RegistroController::class, 'procesarForm'], name: 'registro.store');
        $router->get('/activar/{token}', [RegistroController::class, 'activar'], name: 'registro.activar');

        // Registro público de aspirantes (postulantes a guardia) desde el modal de login
        $router->post('/aspirantes', [AspiranteController::class, 'registrar'], name: 'aspirantes.store');
        // Verificación de email (enlace mágico) — pública
        $router->get('/verificar', [VerificacionController::class, 'verificar'], name: 'aspirantes.verificar');

        // Reclutamiento — revisión y aprobación de aspirantes (Fase 4, protegida)
        $router->get('/reclutamiento', [AspiranteAdminController::class, 'index'], name: 'reclutamiento.index', permission: 'aspirantes.gestionar');
        $router->get('/reclutamiento/{id}', [AspiranteAdminController::class, 'show'], name: 'reclutamiento.show', permission: 'aspirantes.gestionar');
        $router->post('/reclutamiento/{id}/aprobar', [AspiranteAdminController::class, 'aprobar'], name: 'reclutamiento.aprobar', permission: 'aspirantes.gestionar');
        $router->post('/reclutamiento/{id}/rechazar', [AspiranteAdminController::class, 'rechazar'], name: 'reclutamiento.rechazar', permission: 'aspirantes.gestionar');

        // --- Healthcheck publico y smoke tests solo fuera de produccion ---
        $router->get('/healthcheck', static function (
            Config $config,
            SessionManager $session,
            CsrfGuard $csrf,
            Authenticator $auth,
        ) {
            $payload = [
                'status' => 'ok',
                'service' => 'DigiSecure',
                'timestamp' => date('c'),
            ];

            if ($config->string('app.env', 'production') !== 'production') {
                $payload += [
                    'phase' => '18.0',
                    'php' => PHP_VERSION,
                    'session_active' => $session->isStarted(),
                    'csrf_token_present' => $csrf->token() !== '',
                    'authenticated' => $auth->isAuthenticated(),
                ];
            }

            return \DigiSecure\Kernel\Http\Response::json($payload);
        }, name: 'healthcheck');

        if ($config->string('app.env', 'production') !== 'production') {
            $router->get('/_smoke/session', static function (SessionManager $session, CsrfGuard $csrf) {
                $hits = (int)($session->get('_smoke.hits', 0)) + 1;
                $session->set('_smoke.hits', $hits);
                return \DigiSecure\Kernel\Http\Response::json([
                    'hits' => $hits,
                    'csrf_token_present' => $csrf->token() !== '',
                ]);
            }, name: 'smoke.session');
        }
    }
}
