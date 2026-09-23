<?php

declare(strict_types=1);

namespace DigiSecure\Tenant\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Notification\Application\Mailer;
use DigiSecure\Security\Crypto\Hasher;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Subscription\Infrastructure\PlanRepository;
use DigiSecure\Subscription\Infrastructure\SuscripcionCicloRepository;
use DigiSecure\Tenant\Domain\TenantContext;
use DigiSecure\Tenant\Domain\ValidadorRuc;
use DigiSecure\Tenant\Infrastructure\RegistroEmpresaRepository;

/**
 * Registra una nueva empresa desde el portal público.
 *
 * Flujo:
 *  1. Valida entrada (RUC, email, password, datos básicos)
 *  2. Aplica rate limit por IP
 *  3. Crea tenant en estado 'pendiente' con plan trial
 *  4. Crea usuario administrador en estado 'inactivo' (se activa al abrir el link)
 *  5. Asigna rol admin_tenant
 *  6. Registra ciclo inicial trial (30 días, cortesía)
 *  7. Genera token de activación (32 bytes hex, hash SHA256 en DB)
 *  8. Encola email de activación con enlace /activar/{token}
 *  9. Audita en registros_empresa
 *
 * TODO el flujo corre en una transacción para garantizar atomicidad.
 */
final class RegistrarTenantService
{
    /** ULID del rol admin_tenant (seed) */
    private const ROL_ADMIN_TENANT_ID = 'SEEDRBC0000000000000000002';

    private const PLAN_TRIAL_CODIGO = 'trial';
    private const TRIAL_DIAS = 30;
    private const TOKEN_BYTES = 32;
    private const TOKEN_EXPIRACION_HORAS = 48;
    private const RATE_LIMIT_VENTANA_MIN = 60;
    private const RATE_LIMIT_MAX_POR_IP = 5;

    public function __construct(
        private readonly PdoConnection $db,
        private readonly Hasher $hasher,
        private readonly PlanRepository $planRepo,
        private readonly SuscripcionCicloRepository $ciclosRepo,
        private readonly RegistroEmpresaRepository $registrosRepo,
        private readonly Mailer $mailer,
        private readonly TenantContext $tenantContext,
        private readonly Config $config,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{tenant_id: string, usuario_id: string, email: string, expira_at: string}
     */
    public function execute(array $input, string $ip, string $userAgent): array
    {
        // 1. Rate limit por IP (antes de validar lo demás).
        if ($this->registrosRepo->contarIntentosRecientes($ip, self::RATE_LIMIT_VENTANA_MIN) >= self::RATE_LIMIT_MAX_POR_IP) {
            $this->registrosRepo->registrar(
                evento: 'fallo',
                emailAdmin: (string) ($input['email'] ?? ''),
                ruc: (string) ($input['ruc'] ?? ''),
                razonSocial: (string) ($input['razon_social'] ?? ''),
                ip: $ip,
                userAgent: $userAgent,
                detalles: ['motivo' => 'rate_limit_ip'],
            );
            throw new DomainException('Has superado el número de registros permitidos desde esta IP. Intenta nuevamente en una hora.');
        }

        // 2. Normalizar y validar.
        $ruc         = preg_replace('/\D/', '', (string) ($input['ruc'] ?? '')) ?? '';
        $razonSocial = trim((string) ($input['razon_social'] ?? ''));
        $email       = trim((string) ($input['email'] ?? ''));
        $emailNorm   = mb_strtolower($email, 'UTF-8');
        $telefono    = trim((string) ($input['telefono'] ?? '')) ?: null;
        $ciudad      = trim((string) ($input['ciudad'] ?? '')) ?: null;
        $nombres     = trim((string) ($input['admin_nombres'] ?? ''));
        $apellidos   = trim((string) ($input['admin_apellidos'] ?? ''));
        $password    = (string) ($input['password'] ?? '');
        $passwordRep = (string) ($input['password_confirmacion'] ?? '');
        $aceptaTerminos = !empty($input['acepta_terminos']);

        $this->validarEntrada(
            $ruc,
            $razonSocial,
            $emailNorm,
            $nombres,
            $apellidos,
            $password,
            $passwordRep,
            $aceptaTerminos,
        );

        // 3. Validar unicidad (RUC no ocupado, email no registrado).
        if ($this->registrosRepo->existeRucActivo($ruc)) {
            $this->registrosRepo->registrar(
                'fallo', $emailNorm, $ruc, $razonSocial, null, $ip, $userAgent,
                ['motivo' => 'ruc_duplicado'],
            );
            throw new DomainException('El RUC ingresado ya está registrado en DigiSecure. Si perdiste acceso a tu cuenta, usa "¿Olvidaste tu contraseña?".');
        }
        if ($this->registrosRepo->existeEmailAdmin($emailNorm)) {
            $this->registrosRepo->registrar(
                'fallo', $emailNorm, $ruc, $razonSocial, null, $ip, $userAgent,
                ['motivo' => 'email_duplicado'],
            );
            throw new DomainException('El correo ingresado ya está registrado en DigiSecure.');
        }

        // 4. Plan trial disponible.
        $planTrial = $this->planRepo->findByCodigo(self::PLAN_TRIAL_CODIGO);
        if ($planTrial === null || !$planTrial->activo) {
            throw new DomainException('No hay plan trial disponible. Contacta soporte.');
        }

        // 5. Crear todo en una transacción.
        $tenantId   = IdGenerator::ulid();
        $usuarioId  = IdGenerator::ulid();
        $tokenPlano = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash  = hash('sha256', $tokenPlano);
        $now        = Clock::now();
        $nowStr     = $now->format('Y-m-d H:i:s.u');
        $expiraAt   = $now->modify('+' . self::TOKEN_EXPIRACION_HORAS . ' hours');
        $expiraStr  = $expiraAt->format('Y-m-d H:i:s.u');
        $inicioTrial = $now;
        $finTrial    = $now->modify('+' . self::TRIAL_DIAS . ' days');
        $passwordHash = $this->hasher->hash($password);

        $this->db->transactional(function () use (
            $tenantId, $usuarioId, $ruc, $razonSocial, $emailNorm, $email,
            $telefono, $ciudad, $nombres, $apellidos, $passwordHash,
            $tokenHash, $expiraStr, $nowStr, $planTrial,
            $inicioTrial, $finTrial,
        ): void {
            // 5.1 tenants
            $this->db->execute(
                'INSERT INTO tenants
                    (id, razon_social, ruc, email_contacto, telefono, ciudad, pais,
                     estado, plan_codigo, plan_inicio, plan_vencimiento,
                     token_activacion_hash, token_activacion_expira_at,
                     max_usuarios, max_guardias, max_storage_mb,
                     timezone, locale, created_at, updated_at)
                 VALUES
                    (:id, :rs, :ruc, :em, :tel, :ciu, :pa,
                     :es, :pc, :pi, :pv,
                     :thash, :texp,
                     :mu, :mg, :ms,
                     :tz, :lc, :ca, :ua)',
                [
                    'id'    => $tenantId,
                    'rs'    => $razonSocial,
                    'ruc'   => $ruc,
                    'em'    => $emailNorm,
                    'tel'   => $telefono,
                    'ciu'   => $ciudad,
                    'pa'    => 'EC',
                    'es'    => 'pendiente',
                    'pc'    => $planTrial->codigo,
                    'pi'    => $inicioTrial->format('Y-m-d'),
                    'pv'    => $finTrial->format('Y-m-d'),
                    'thash' => $tokenHash,
                    'texp'  => $expiraStr,
                    'mu'    => $planTrial->maxUsuarios,
                    'mg'    => $planTrial->maxGuardias,
                    'ms'    => $planTrial->maxStorageMb,
                    'tz'    => 'America/Guayaquil',
                    'lc'    => 'es_EC',
                    'ca'    => $nowStr,
                    'ua'    => $nowStr,
                ],
            );

            // 5.2 usuarios (admin inicial en estado 'pendiente_verificacion')
            $this->db->execute(
                'INSERT INTO usuarios
                    (id, tenant_id, email, email_normalizado, password_hash,
                     nombres, apellidos, estado, mfa_habilitado,
                     intentos_fallidos, debe_cambiar_password,
                     password_changed_at, created_at, updated_at)
                 VALUES
                    (:id, :tid, :em, :emn, :pw,
                     :no, :ap, :es, 0,
                     0, 0,
                     :pca, :ca, :ua)',
                [
                    'id'  => $usuarioId,
                    'tid' => $tenantId,
                    'em'  => $email,
                    'emn' => $emailNorm,
                    'pw'  => $passwordHash,
                    'no'  => $nombres,
                    'ap'  => $apellidos,
                    'es'  => 'pendiente_verificacion',
                    'pca' => $nowStr,
                    'ca'  => $nowStr,
                    'ua'  => $nowStr,
                ],
            );

            // 5.3 usuarios_roles (asignar admin_tenant)
            $this->db->execute(
                'INSERT INTO usuarios_roles (usuario_id, role_id, assigned_at, assigned_by)
                 VALUES (:uid, :rid, :now, NULL)',
                [
                    'uid' => $usuarioId,
                    'rid' => self::ROL_ADMIN_TENANT_ID,
                    'now' => $nowStr,
                ],
            );

            // 5.4 suscripcion_ciclos (ciclo inicial trial como cortesia)
            // Establecer tenantContext antes de usar ciclosRepo (requireTenantId).
            if (!$this->tenantContext->isSet()) {
                $this->tenantContext->set($tenantId, $razonSocial, 'pendiente');
            }
            $this->ciclosRepo->insertar([
                'plan_codigo'     => $planTrial->codigo,
                'tipo'            => 'inicial',
                'fecha_inicio'    => $inicioTrial->format('Y-m-d'),
                'fecha_fin'       => $finTrial->format('Y-m-d'),
                'precio_usd'      => 0.0,
                'estado_pago'     => 'cortesia',
                'metodo_pago'     => null,
                'referencia_pago' => null,
                'pagado_at'       => $nowStr,
                'notas'           => 'Trial otorgado en registro público.',
                'created_by'      => null,
            ]);
        });

        // 6. Enviar el email de activación en el momento (fuera de la transacción).
        //    No va a la cola: la cola solo se despacha por cron para tenants
        //    activos, y este tenant sigue 'pendiente' hasta activarse.
        $enlace = rtrim($this->config->string('app.url', ''), '/') . '/activar/' . $tokenPlano;
        $nombreCompleto = trim($nombres . ' ' . $apellidos);
        $envio = $this->mailer->enviarDetallado(
            $email,
            $nombreCompleto !== '' ? $nombreCompleto : $email,
            'DigiSecure · Activa tu cuenta empresarial',
            $this->buildTextBody($nombreCompleto, $razonSocial, $enlace),
            $this->buildHtmlBody($nombreCompleto, $razonSocial, $enlace),
        );

        // 7. Auditar
        $this->registrosRepo->registrar(
            evento: 'creado',
            emailAdmin: $emailNorm,
            ruc: $ruc,
            razonSocial: $razonSocial,
            tenantId: $tenantId,
            ip: $ip,
            userAgent: $userAgent,
            detalles: [
                'plan' => $planTrial->codigo,
                'trial_dias' => self::TRIAL_DIAS,
                'expira_at' => $expiraStr,
                'correo_activacion_enviado' => $envio['success'],
                'correo_error' => $envio['success'] ? null : ($envio['code'] ?? 'ERROR'),
            ],
        );

        return [
            'tenant_id' => $tenantId,
            'usuario_id' => $usuarioId,
            'email' => $email,
            'expira_at' => $expiraStr,
        ];
    }

    private function validarEntrada(
        string $ruc,
        string $razonSocial,
        string $emailNorm,
        string $nombres,
        string $apellidos,
        string $password,
        string $passwordRep,
        bool $aceptaTerminos,
    ): void {
        if (!ValidadorRuc::esValido($ruc)) {
            throw new DomainException('El RUC ingresado no es válido. Verifica los 13 dígitos según el formato del SRI.');
        }
        if (mb_strlen($razonSocial) < 3 || mb_strlen($razonSocial) > 255) {
            throw new DomainException('La razón social debe tener entre 3 y 255 caracteres.');
        }
        if ($emailNorm === '' || filter_var($emailNorm, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('El correo electrónico no tiene un formato válido.');
        }
        if (mb_strlen($emailNorm) > 190) {
            throw new DomainException('El correo no puede superar los 190 caracteres.');
        }
        if ($nombres === '' || mb_strlen($nombres) > 120) {
            throw new DomainException('Indica los nombres del administrador (máx. 120 caracteres).');
        }
        if ($apellidos === '' || mb_strlen($apellidos) > 120) {
            throw new DomainException('Indica los apellidos del administrador (máx. 120 caracteres).');
        }
        if (mb_strlen($password) < 10) {
            throw new DomainException('La contraseña debe tener al menos 10 caracteres.');
        }
        if ($password !== $passwordRep) {
            throw new DomainException('Las contraseñas no coinciden.');
        }
        // Complejidad mínima: al menos 1 letra y 1 número.
        if (!preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]/u', $password) || !preg_match('/\d/', $password)) {
            throw new DomainException('La contraseña debe incluir al menos una letra y un número.');
        }
        if (!$aceptaTerminos) {
            throw new DomainException('Debes aceptar los términos y condiciones para registrarte.');
        }
    }

    private function buildTextBody(string $nombre, string $empresa, string $enlace): string
    {
        $horas = self::TOKEN_EXPIRACION_HORAS;
        $dias  = self::TRIAL_DIAS;
        $saludo = $nombre !== '' ? "Hola {$nombre}," : 'Hola,';

        return <<<TEXT
{$saludo}

Recibimos tu solicitud para registrar la empresa "{$empresa}" en DigiSecure.

Para activar tu cuenta haz clic en el siguiente enlace:
{$enlace}

Este enlace expira en {$horas} horas.

Al activar tu cuenta obtendrás:
  • {$dias} días gratis del plan Trial
  • Acceso al módulo de guardias y expedientes
  • Notificaciones de vencimientos y cumplimiento LOPDP

Si tú no solicitaste este registro, ignora este mensaje.

— Equipo DigiSecure
Seguridad privada para Ecuador
TEXT;
    }

    private function buildHtmlBody(string $nombre, string $empresa, string $enlace): string
    {
        $nombreEsc  = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $empresaEsc = htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8');
        $enlaceEsc  = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');
        $horas = self::TOKEN_EXPIRACION_HORAS;
        $dias  = self::TRIAL_DIAS;
        $saludo = $nombreEsc !== '' ? "Hola <strong>{$nombreEsc}</strong>," : 'Hola,';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Activa tu cuenta DigiSecure</title></head>
<body style="font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:24px;color:#2c3e50">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden">
  <div style="background:#1a3a5c;color:#fff;padding:28px 24px;text-align:center">
    <div style="font-size:32px;margin-bottom:6px">🛡️</div>
    <h2 style="margin:0;font-size:22px;letter-spacing:1px">DigiSecure</h2>
    <div style="opacity:.85;font-size:13px;margin-top:4px">Activa tu cuenta empresarial</div>
  </div>
  <div style="padding:28px 24px">
    <p>{$saludo}</p>
    <p>Recibimos tu solicitud para registrar la empresa <strong>{$empresaEsc}</strong> en DigiSecure.</p>
    <p style="margin:28px 0;text-align:center">
      <a href="{$enlaceEsc}" style="display:inline-block;background:#2e86de;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-weight:bold;font-size:15px">
        ✔ Activar mi cuenta
      </a>
    </p>
    <p style="color:#6c757d;font-size:13px">Este enlace expira en <strong>{$horas} horas</strong>.</p>
    <div style="background:#f1f6fb;border-left:4px solid #2e86de;padding:14px 16px;margin:20px 0;border-radius:4px;font-size:14px">
      Al activar tu cuenta obtendrás:
      <ul style="margin:8px 0 0 0;padding-left:20px">
        <li><strong>{$dias} días gratis</strong> del plan Trial</li>
        <li>Gestión de guardias y expedientes</li>
        <li>Alertas de vencimientos y cumplimiento LOPDP</li>
      </ul>
    </div>
    <p style="color:#6c757d;font-size:12px">Si el botón no funciona, copia y pega esta URL en tu navegador:</p>
    <p style="word-break:break-all;font-size:12px;background:#f8f9fa;padding:8px 12px;border-radius:4px;color:#495057">{$enlaceEsc}</p>
    <hr style="border:none;border-top:1px solid #e9ecef;margin:24px 0">
    <p style="color:#6c757d;font-size:12px">
      Si tú no solicitaste este registro, ignora este mensaje. No se creará ninguna cuenta activa a tu nombre.
    </p>
  </div>
  <div style="background:#f8f9fa;padding:12px 24px;font-size:11px;color:#6c757d;text-align:center">
    — Equipo DigiSecure · Seguridad privada para Ecuador
  </div>
</div>
</body>
</html>
HTML;
    }
}
