<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Application;

/**
 * Deja constancia de un correo transaccional que no se pudo enviar.
 *
 * Los cuerpos de estos correos llevan enlaces con token (recuperar
 * contraseña, verificar correo, activar empresa): quien lea el log podría
 * usarlos. Por eso el contenido solo se escribe cuando $incluirContenido es
 * true (entornos de desarrollo/QA sin MTA, para poder seguir el flujo). En
 * producción queda solo metadata: destinatario enmascarado, código, motivo y
 * asunto. El error_log del servidor nunca recibe el contenido.
 */
final class RegistroEnvioFallido
{
    public function __construct(
        private readonly string $logPath,
        private readonly bool $incluirContenido,
        private readonly string $etiqueta,
    ) {
    }

    public function registrar(
        string $correo,
        ?string $codigo,
        ?string $motivo,
        string $asunto,
        string $contenido,
    ): void {
        $resumen = sprintf(
            'Fallo enviando a %s (%s): %s. Asunto: %s',
            self::enmascararCorreo($correo),
            $codigo ?? 'ERROR',
            $motivo ?? 'sin detalle',
            $asunto,
        );

        $linea = $this->incluirContenido
            ? $resumen . "\n  Destinatario: {$correo}\n  --- Contenido ---\n{$contenido}"
            : $resumen;

        @file_put_contents($this->logPath, '[' . date('Y-m-d H:i:s') . "] {$linea}\n", FILE_APPEND);
        error_log("[DIGISECURE][{$this->etiqueta}] {$resumen}");
    }

    /**
     * "leninpatricio@gmail.com" -> "le***@gmail.com".
     */
    public static function enmascararCorreo(string $correo): string
    {
        $arroba = strrpos($correo, '@');
        if ($arroba === false) {
            return '***';
        }

        $usuario = substr($correo, 0, $arroba);
        $visible = mb_substr($usuario, 0, min(2, max(0, mb_strlen($usuario) - 1)));

        return $visible . '***' . substr($correo, $arroba);
    }
}
