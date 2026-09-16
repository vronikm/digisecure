<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Application;

use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;

/**
 * Corrección de los datos de registro de un aspirante desde reclutamiento
 * (derecho de rectificación, LOPDP).
 *
 * Caso típico: el postulante escribió mal su correo, el enlace de verificación
 * nunca le llegó y no puede continuar. Al corregir el correo de un aspirante
 * sin verificar se emite un token nuevo (48 h) que reemplaza al anterior; el
 * controlador envía el enlace, registra los eventos y audita.
 *
 * Reglas:
 *  - Solo antes de la contratación (estados en ESTADOS_EDITABLES).
 *  - Mismas validaciones que el registro público (ValidadorDatosAspirante).
 *  - Un correo nuevo debe confirmarse dos veces y no puede pertenecer a otro
 *    aspirante ni a otro usuario.
 *  - Si el aspirante ya tiene usuario, su correo de inicio de sesión, nombres
 *    y teléfono se actualizan en la misma transacción.
 *  - El documento no cambia si el aspirante ya creó su ficha (la ficha guarda
 *    su propia cédula cifrada).
 */
final class ActualizarAspiranteService
{
    public const ESTADOS_EDITABLES = ['nuevo', 'aspirante', 'en_revision', 'contactado'];

    private const TOKEN_BYTES = 32;
    private const TOKEN_EXPIRACION_HORAS = 48;

    public function __construct(
        private readonly AspiranteRepository $repo,
        private readonly ValidadorDatosAspirante $validador,
    ) {
    }

    /**
     * @param array<string, mixed> $aspirante Fila de `aspirantes`.
     */
    public static function esEditable(array $aspirante): bool
    {
        return in_array((string) ($aspirante['estado'] ?? ''), self::ESTADOS_EDITABLES, true);
    }

    /**
     * @param array<string, mixed> $input Campos del formulario + `correo_confirmacion`
     *                                    y `reenviar_enlace` (bool).
     * @return array{
     *     cambios: list<string>,
     *     nombre_completo: string,
     *     correo: string,
     *     correo_anterior: string,
     *     correo_cambiado: bool,
     *     usuario_actualizado: bool,
     *     verificacion_token: ?string
     * }
     */
    public function execute(string $aspiranteId, array $input): array
    {
        $a = $this->repo->findDetalle($aspiranteId);
        if ($a === null) {
            throw new DomainException('Aspirante no encontrado.');
        }
        if (!self::esEditable($a)) {
            throw new DomainException(
                'Los datos de un aspirante contratado o descartado no se editan desde reclutamiento. '
                . 'Corrígelos desde Usuarios o Guardias.'
            );
        }

        $datos = $this->validador->normalizar($input);
        $this->validador->validar($datos);

        $actual = [
            'tipo_documento'   => (string) $a['tipo_documento'],
            'documento'        => (string) $a['documento'],
            'apellidos'        => (string) $a['apellidos'],
            'nombres'          => (string) $a['nombres'],
            'celular'          => (string) $a['celular'],
            'correo'           => (string) $a['correo'],
            'estatura_cm'      => (int) $a['estatura_cm'],
            'fecha_nacimiento' => (string) $a['fecha_nacimiento'],
        ];
        $nuevo = [
            'tipo_documento'   => $datos['tipo_documento']->value,
            'documento'        => $datos['documento'],
            'apellidos'        => $datos['apellidos'],
            'nombres'          => $datos['nombres'],
            'celular'          => $datos['celular'],
            'correo'           => $datos['correo'],
            'estatura_cm'      => $datos['estatura_cm'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'],
        ];

        $cambios = [];
        foreach ($nuevo as $campo => $valor) {
            if ($valor !== $actual[$campo]) {
                $cambios[] = $campo;
            }
        }

        $usuarioId = trim((string) ($a['usuario_id'] ?? ''));
        $usuarioId = $usuarioId !== '' ? $usuarioId : null;
        $verificado = (int) ($a['email_verificado'] ?? 0) === 1;
        $correoCambiado = $datos['correo_normalizado'] !== (string) $a['correo_normalizado'];
        $emitirToken = !$verificado && ($correoCambiado || !empty($input['reenviar_enlace']));

        if (in_array('tipo_documento', $cambios, true) || in_array('documento', $cambios, true)) {
            $this->validarCambioDocumento($aspiranteId, $datos['documento'], $usuarioId);
        }
        if ($correoCambiado) {
            $this->validarCambioCorreo($aspiranteId, $datos['correo_normalizado'], $input['correo_confirmacion'] ?? null, $usuarioId);
        }

        $resultado = [
            'cambios'             => $cambios,
            'nombre_completo'     => trim($datos['nombres'] . ' ' . $datos['apellidos']),
            'correo'              => $datos['correo'],
            'correo_anterior'     => $actual['correo'],
            'correo_cambiado'     => $correoCambiado,
            'usuario_actualizado' => false,
            'verificacion_token'  => null,
        ];

        if ($cambios === [] && !$emitirToken) {
            return $resultado;
        }

        $now = Clock::now();
        $cuando = $now->format('Y-m-d H:i:s.u');
        $token = null;
        $tokenPlano = null;
        if ($emitirToken) {
            $tokenPlano = bin2hex(random_bytes(self::TOKEN_BYTES));
            $token = [
                'hash'      => hash('sha256', $tokenPlano),
                'expira_at' => $now->modify('+' . self::TOKEN_EXPIRACION_HORAS . ' hours')->format('Y-m-d H:i:s.u'),
            ];
        }

        if ($cambios === []) {
            // Solo reenvío: el enlace anterior (quizá vencido) queda invalidado.
            $this->repo->regenerarTokenVerificacion($aspiranteId, $token['hash'], $token['expira_at'], $cuando);
        } else {
            $this->repo->actualizarDatos(
                $aspiranteId,
                [
                    'tipo_documento'     => $nuevo['tipo_documento'],
                    'documento'          => $nuevo['documento'],
                    'apellidos'          => $nuevo['apellidos'],
                    'nombres'            => $nuevo['nombres'],
                    'celular'            => $nuevo['celular'],
                    'correo'             => $nuevo['correo'],
                    'correo_normalizado' => $datos['correo_normalizado'],
                    'estatura_cm'        => $nuevo['estatura_cm'],
                    'fecha_nacimiento'   => $nuevo['fecha_nacimiento'],
                ],
                $token,
                $usuarioId,
                $cuando,
            );
            $resultado['usuario_actualizado'] = $usuarioId !== null;
        }

        $resultado['verificacion_token'] = $tokenPlano;

        return $resultado;
    }

    private function validarCambioDocumento(string $aspiranteId, string $documento, ?string $usuarioId): void
    {
        if ($usuarioId !== null && $this->repo->findFichaDeUsuario($usuarioId) !== null) {
            throw new DomainException(
                'El aspirante ya creó su ficha de ingreso: el documento no se puede cambiar desde reclutamiento.'
            );
        }
        if ($this->repo->existeDocumentoEnOtro($documento, $aspiranteId)) {
            throw new DomainException('Ya existe otra postulación registrada con este documento.');
        }
    }

    private function validarCambioCorreo(string $aspiranteId, string $correoNorm, mixed $confirmacion, ?string $usuarioId): void
    {
        $confirmacionNorm = is_scalar($confirmacion) ? mb_strtolower(trim((string) $confirmacion), 'UTF-8') : '';
        if ($confirmacionNorm !== $correoNorm) {
            throw new DomainException('Confirma el correo nuevo: ambos campos deben coincidir.');
        }

        $this->validador->validarDominioPermitido($correoNorm);

        if ($this->repo->existeCorreoEnOtro($correoNorm, $aspiranteId)
            || $this->repo->existeCorreoEnOtroUsuario($correoNorm, $usuarioId)
        ) {
            throw new DomainException('Ese correo ya está registrado por otra persona en el sistema.');
        }
    }
}
