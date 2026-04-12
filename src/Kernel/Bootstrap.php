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
use DigiSecure\Kernel\Middleware\SecurityHeadersMiddleware;
use DigiSecure\Kernel\Middleware\SessionMiddleware;
use DigiSecure\Kernel\Middleware\TenantContextMiddleware;
use DigiSecure\Kernel\Routing\RouteDispatcher;
use DigiSecure\Kernel\Routing\Router;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Auth\Authenticator;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Security\Crypto\Hasher;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Security\Rbac\Authorizer;
use DigiSecure\Security\Session\SessionManager;
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

        // 13. Módulo Reporting (KPIs / Dashboard Stats)
        $container->singleton(
            \DigiSecure\Reporting\Application\DashboardStatsService::class,
            static fn(Container $c) => new \DigiSecure\Reporting\Application\DashboardStatsService(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
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

        // 17. Módulo Roles (Fase 4 — solo lectura)
        $container->singleton(
            \DigiSecure\Security\Rbac\RoleRepository::class,
            static fn(Container $c) => new \DigiSecure\Security\Rbac\RoleRepository(
                $c->get(PdoConnection::class),
                $c->get(TenantContext::class),
            ),
        );

        // 14. Router con rutas registradas
        $router = new Router();
        $this->registerRoutes($router);
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
        //  - Authorization verifica permisos de ruta.
        //  - Audit registra operaciones de escritura.
        $globalMiddlewares = [
            SecurityHeadersMiddleware::class,
            ErrorHandlerMiddleware::class,
            SessionMiddleware::class,
            CsrfMiddleware::class,
            AuthenticationMiddleware::class,
            TenantContextMiddleware::class,
            AuthorizationMiddleware::class,
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
                'collation' => $envVars['DB_COLLATION'] ?? 'utf8mb4_0900_ai_ci',
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

    private function registerRoutes(Router $router): void
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

        // --- Rutas protegidas ---
        $router->post('/logout', [LogoutController::class, 'logout'], name: 'logout');
        $router->get('/dashboard', [DashboardController::class, 'index'], name: 'dashboard');

        // Perfil de usuario
        $router->get('/perfil', [ProfileController::class, 'show'], name: 'perfil');
        $router->get('/perfil/password', [ProfileController::class, 'showPasswordForm'], name: 'perfil.password');
        $router->post('/perfil/password', [ProfileController::class, 'changePassword'], name: 'perfil.password.change');

        // Fichas de guardia (Fase 1)
        $router->get('/guardias', [FichaController::class, 'index'], name: 'fichas.index');
        $router->get('/guardias/crear', [FichaController::class, 'create'], name: 'fichas.create');
        $router->post('/guardias/crear', [FichaController::class, 'store'], name: 'fichas.store');
        $router->get('/guardias/{id}', [FichaController::class, 'show'], name: 'fichas.show');
        $router->get('/guardias/{id}/editar', [FichaController::class, 'edit'], name: 'fichas.edit');
        $router->post('/guardias/{id}/editar', [FichaController::class, 'update'], name: 'fichas.update');
        $router->post('/guardias/{id}/completar', [FichaController::class, 'completar'], name: 'fichas.completar');

        // Expediente documental (Fase 2)
        $router->get('/expedientes', [ExpedienteController::class, 'index'], name: 'expedientes.index');
        $router->get('/expedientes/pendientes', [ExpedienteController::class, 'pendientes'], name: 'expedientes.pendientes');
        $router->get('/guardias/{fichaId}/expediente', [ExpedienteController::class, 'dashboard'], name: 'expedientes.dashboard');
        $router->get('/expedientes/{id}/documento/{tipoId}', [ExpedienteController::class, 'showUploadForm'], name: 'expedientes.documento.upload.form');
        $router->post('/expedientes/{id}/documento/{tipoId}', [ExpedienteController::class, 'upload'], name: 'expedientes.documento.upload');
        $router->get('/expedientes/documento/{docId}', [ExpedienteController::class, 'showDocumento'], name: 'expedientes.documento.show');
        $router->post('/expedientes/documento/{docId}/revisar', [ExpedienteController::class, 'revisar'], name: 'expedientes.documento.revisar');

        // Reportes y KPIs (Fase 3)
        $router->get('/reportes/kpis', [KpiController::class, 'dashboard'], name: 'reportes.kpis');
        $router->get('/reportes/exportar', [KpiController::class, 'exportar'], name: 'reportes.exportar');

        // Administración de usuarios (Fase 4)
        $router->get('/usuarios', [UserController::class, 'index'], name: 'usuarios.index');
        $router->get('/usuarios/crear', [UserController::class, 'create'], name: 'usuarios.create');
        $router->post('/usuarios/crear', [UserController::class, 'store'], name: 'usuarios.store');
        $router->get('/usuarios/{id}/editar', [UserController::class, 'edit'], name: 'usuarios.edit');
        $router->post('/usuarios/{id}/editar', [UserController::class, 'update'], name: 'usuarios.update');
        $router->post('/usuarios/{id}/estado', [UserController::class, 'cambiarEstado'], name: 'usuarios.estado');
        $router->post('/usuarios/{id}/password', [UserController::class, 'resetPassword'], name: 'usuarios.password');

        // Auditoría (Fase 4 — read-only)
        $router->get('/auditoria', [AuditoriaController::class, 'index'], name: 'auditoria.index');

        // Roles y permisos (Fase 4 — read-only)
        $router->get('/roles', [RoleController::class, 'index'], name: 'roles.index');
        $router->get('/roles/{id}', [RoleController::class, 'show'], name: 'roles.show');

        // --- Healthcheck y smoke tests (mantener para monitoreo) ---
        $router->get('/healthcheck', static fn(
            SessionManager $session,
            CsrfGuard $csrf,
            Authenticator $auth,
        ) => \DigiSecure\Kernel\Http\Response::json([
            'status' => 'ok',
            'service' => 'DigiSecure',
            'phase' => '5.0',
            'timestamp' => date('c'),
            'php' => PHP_VERSION,
            'session_active' => $session->isStarted(),
            'csrf_token_present' => $csrf->token() !== '',
            'authenticated' => $auth->isAuthenticated(),
        ]), name: 'healthcheck');

        $router->get('/_smoke/session', static function (SessionManager $session, CsrfGuard $csrf) {
            $hits = (int)($session->get('_smoke.hits', 0)) + 1;
            $session->set('_smoke.hits', $hits);
            return \DigiSecure\Kernel\Http\Response::json([
                'session_id' => $session->id(),
                'hits' => $hits,
                'csrf_token' => $csrf->token(),
            ]);
        }, name: 'smoke.session');
    }
}
