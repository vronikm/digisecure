<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Aspirante;

use DateTimeImmutable;
use DateTimeZone;
use DigiSecure\Aspirante\Application\ActualizarAspiranteService;
use DigiSecure\Aspirante\Application\ValidadorDatosAspirante;
use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActualizarAspiranteServiceTest extends TestCase
{
    private const ID = '01TESTASPIRANTE00000000001';
    private const CORREO_MAL = 'juanperzgomez@example.com';
    private const CORREO_BIEN = 'juanperezgomez@example.com';

    private EdicionAspiranteRepositoryFake $repo;
    private ActualizarAspiranteService $service;

    protected function setUp(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-09-16 12:00:00', new DateTimeZone('UTC')));
        $this->repo = new EdicionAspiranteRepositoryFake($this->aspirante());
        $this->service = new ActualizarAspiranteService($this->repo, new ValidadorDatosAspirante(['tempmail.com']));
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
    }

    /**
     * Aspirante recien registrado con el correo mal escrito (datos ficticios).
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function aspirante(array $overrides = []): array
    {
        return array_merge([
            'id'                  => self::ID,
            'tipo_documento'      => 'cedula',
            'documento'           => '1710034065',
            'apellidos'           => 'Perez Gomez',
            'nombres'             => 'Juan Carlos',
            'celular'             => '0991234567',
            'correo'              => self::CORREO_MAL,
            'correo_normalizado'  => self::CORREO_MAL,
            'estatura_cm'         => 172,
            'fecha_nacimiento'    => '1995-03-10',
            'estado'              => 'nuevo',
            'email_verificado'    => 0,
            'usuario_id'          => null,
        ], $overrides);
    }

    /**
     * Formulario con los datos actuales (lo que envia la vista sin tocar nada).
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'tipo_documento'      => 'cedula',
            'documento'           => '1710034065',
            'apellidos'           => 'Perez Gomez',
            'nombres'             => 'Juan Carlos',
            'celular'             => '0991234567',
            'correo'              => self::CORREO_MAL,
            'correo_confirmacion' => '',
            'estatura'            => '1.72',
            'fecha_nacimiento'    => '1995-03-10',
            'reenviar_enlace'     => false,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function corregirCorreo(): array
    {
        return $this->form(['correo' => self::CORREO_BIEN, 'correo_confirmacion' => self::CORREO_BIEN]);
    }

    #[Test]
    public function corrige_correo_sin_verificar_y_emite_un_token_nuevo(): void
    {
        $r = $this->service->execute(self::ID, $this->corregirCorreo());

        self::assertSame(['correo'], $r['cambios']);
        self::assertTrue($r['correo_cambiado']);
        self::assertSame(self::CORREO_MAL, $r['correo_anterior']);
        self::assertSame(self::CORREO_BIEN, $r['correo']);
        self::assertFalse($r['usuario_actualizado']);
        self::assertNotNull($r['verificacion_token']);

        self::assertCount(1, $this->repo->actualizaciones);
        $upd = $this->repo->actualizaciones[0];
        self::assertSame(self::CORREO_BIEN, $upd['datos']['correo']);
        self::assertSame(self::CORREO_BIEN, $upd['datos']['correo_normalizado']);
        self::assertNull($upd['usuario_id']);
        self::assertSame(hash('sha256', (string) $r['verificacion_token']), $upd['token']['hash']);
        self::assertSame('2026-09-18 12:00:00.000000', $upd['token']['expira_at']);
    }

    #[Test]
    public function exige_confirmar_el_correo_nuevo(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Confirma el correo nuevo');
        $this->service->execute(self::ID, $this->form([
            'correo' => self::CORREO_BIEN,
            'correo_confirmacion' => 'juanperezgomes@example.com',
        ]));
    }

    #[Test]
    public function la_confirmacion_ignora_mayusculas_y_espacios(): void
    {
        $r = $this->service->execute(self::ID, $this->form([
            'correo' => self::CORREO_BIEN,
            'correo_confirmacion' => '  JuanPerezGomez@Example.com ',
        ]));

        self::assertTrue($r['correo_cambiado']);
    }

    #[Test]
    public function rechaza_correo_de_otro_aspirante(): void
    {
        $this->repo->correosOtrosAspirantes[] = self::CORREO_BIEN;

        $this->expectException(DomainException::class);
        $this->service->execute(self::ID, $this->corregirCorreo());
    }

    #[Test]
    public function rechaza_correo_de_otro_usuario(): void
    {
        $this->repo->correosOtrosUsuarios[] = self::CORREO_BIEN;

        $this->expectException(DomainException::class);
        $this->service->execute(self::ID, $this->corregirCorreo());
    }

    #[Test]
    public function rechaza_dominio_bloqueado(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute(self::ID, $this->form([
            'correo' => 'juan@tempmail.com',
            'correo_confirmacion' => 'juan@tempmail.com',
        ]));
    }

    #[Test]
    public function aspirante_verificado_con_usuario_sincroniza_login_sin_emitir_token(): void
    {
        $this->repo->aspirante = $this->aspirante([
            'estado' => 'aspirante',
            'email_verificado' => 1,
            'usuario_id' => 'USR01',
        ]);

        $r = $this->service->execute(self::ID, array_merge($this->corregirCorreo(), ['reenviar_enlace' => true]));

        self::assertTrue($r['usuario_actualizado']);
        self::assertNull($r['verificacion_token']);
        self::assertSame('USR01', $this->repo->actualizaciones[0]['usuario_id']);
        self::assertNull($this->repo->actualizaciones[0]['token']);
    }

    #[Test]
    public function sin_cambios_no_escribe_nada(): void
    {
        $r = $this->service->execute(self::ID, $this->form());

        self::assertSame([], $r['cambios']);
        self::assertNull($r['verificacion_token']);
        self::assertSame([], $this->repo->actualizaciones);
        self::assertSame([], $this->repo->tokensRegenerados);
    }

    #[Test]
    public function reenvio_sin_cambios_solo_regenera_el_token(): void
    {
        $r = $this->service->execute(self::ID, $this->form(['reenviar_enlace' => true]));

        self::assertSame([], $r['cambios']);
        self::assertNotNull($r['verificacion_token']);
        self::assertSame([], $this->repo->actualizaciones);
        self::assertCount(1, $this->repo->tokensRegenerados);
        self::assertSame(hash('sha256', (string) $r['verificacion_token']), $this->repo->tokensRegenerados[0]['hash']);
    }

    #[Test]
    public function reenvio_se_ignora_si_el_correo_ya_esta_verificado(): void
    {
        $this->repo->aspirante = $this->aspirante(['estado' => 'aspirante', 'email_verificado' => 1, 'usuario_id' => 'USR01']);

        $r = $this->service->execute(self::ID, $this->form(['reenviar_enlace' => true]));

        self::assertNull($r['verificacion_token']);
        self::assertSame([], $this->repo->tokensRegenerados);
    }

    #[Test]
    public function cambio_solo_de_mayusculas_no_exige_confirmacion_ni_token(): void
    {
        $r = $this->service->execute(self::ID, $this->form(['correo' => 'JuanPerzGomez@example.com']));

        self::assertSame(['correo'], $r['cambios']);
        self::assertFalse($r['correo_cambiado']);
        self::assertNull($r['verificacion_token']);
    }

    #[Test]
    public function corrige_nombres_y_normaliza_datos(): void
    {
        $r = $this->service->execute(self::ID, $this->form([
            'nombres' => '  Juan   Carlos ',
            'apellidos' => 'Pérez Gómez',
            'celular' => '+593 99 765 4321',
            'estatura' => '175',
        ]));

        self::assertSame(['apellidos', 'celular', 'estatura_cm'], $r['cambios']);
        $datos = $this->repo->actualizaciones[0]['datos'];
        self::assertSame('Pérez Gómez', $datos['apellidos']);
        self::assertSame('0997654321', $datos['celular']);
        self::assertSame(175, $datos['estatura_cm']);
    }

    #[Test]
    public function aplica_las_reglas_del_registro(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute(self::ID, $this->form(['celular' => '12345']));
    }

    #[Test]
    public function corrige_documento_antes_de_crear_la_ficha(): void
    {
        $r = $this->service->execute(self::ID, $this->form(['documento' => '0900000001']));

        self::assertSame(['documento'], $r['cambios']);
        self::assertSame('0900000001', $this->repo->actualizaciones[0]['datos']['documento']);
    }

    #[Test]
    public function no_cambia_documento_si_ya_tiene_ficha(): void
    {
        $this->repo->aspirante = $this->aspirante(['estado' => 'aspirante', 'email_verificado' => 1, 'usuario_id' => 'USR01']);
        $this->repo->fichasPorUsuario['USR01'] = ['id' => 'FICHA01'];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ficha de ingreso');
        $this->service->execute(self::ID, $this->form(['documento' => '0900000001']));
    }

    #[Test]
    public function rechaza_documento_de_otra_postulacion(): void
    {
        $this->repo->documentosOtrosAspirantes[] = '0900000001';

        $this->expectException(DomainException::class);
        $this->service->execute(self::ID, $this->form(['documento' => '0900000001']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function estadosNoEditables(): iterable
    {
        yield 'contratado' => ['contratado'];
        yield 'aprobado' => ['aprobado'];
        yield 'descartado' => ['descartado'];
    }

    #[Test]
    #[DataProvider('estadosNoEditables')]
    public function no_edita_despues_de_la_contratacion_o_descarte(string $estado): void
    {
        $this->repo->aspirante = $this->aspirante(['estado' => $estado]);

        $this->expectException(DomainException::class);
        try {
            $this->service->execute(self::ID, $this->corregirCorreo());
        } finally {
            self::assertSame([], $this->repo->actualizaciones);
        }
    }

    #[Test]
    public function aspirante_inexistente(): void
    {
        $this->repo->aspirante = null;

        $this->expectException(DomainException::class);
        $this->service->execute(self::ID, $this->corregirCorreo());
    }
}

/**
 * Doble en memoria para la edicion: omite el constructor real (sin BD) y
 * captura las escrituras.
 */
final class EdicionAspiranteRepositoryFake extends AspiranteRepository
{
    /** @var list<array{datos: array<string, mixed>, token: ?array{hash: string, expira_at: string}, usuario_id: ?string}> */
    public array $actualizaciones = [];
    /** @var list<array{hash: string, expira_at: string}> */
    public array $tokensRegenerados = [];
    /** @var list<string> */
    public array $documentosOtrosAspirantes = [];
    /** @var list<string> */
    public array $correosOtrosAspirantes = [];
    /** @var list<string> */
    public array $correosOtrosUsuarios = [];
    /** @var array<string, array<string, mixed>> */
    public array $fichasPorUsuario = [];

    /**
     * @param array<string, mixed>|null $aspirante
     */
    public function __construct(public ?array $aspirante)
    {
        // Intencionalmente NO llamamos al constructor padre (sin DB en tests).
    }

    public function findDetalle(string $id): ?array
    {
        return $this->aspirante !== null && $this->aspirante['id'] === $id ? $this->aspirante : null;
    }

    public function findFichaDeUsuario(string $usuarioId): ?array
    {
        return $this->fichasPorUsuario[$usuarioId] ?? null;
    }

    public function existeDocumentoEnOtro(string $documento, string $exceptoAspiranteId): bool
    {
        return in_array($documento, $this->documentosOtrosAspirantes, true);
    }

    public function existeCorreoEnOtro(string $correoNormalizado, string $exceptoAspiranteId): bool
    {
        return in_array($correoNormalizado, $this->correosOtrosAspirantes, true);
    }

    public function existeCorreoEnOtroUsuario(string $correoNormalizado, ?string $exceptoUsuarioId): bool
    {
        return in_array($correoNormalizado, $this->correosOtrosUsuarios, true);
    }

    public function actualizarDatos(string $aspiranteId, array $datos, ?array $token, ?string $usuarioId, string $cuando): void
    {
        $this->actualizaciones[] = ['datos' => $datos, 'token' => $token, 'usuario_id' => $usuarioId];
    }

    public function regenerarTokenVerificacion(string $aspiranteId, string $tokenHash, string $expira, string $cuando): void
    {
        $this->tokensRegenerados[] = ['hash' => $tokenHash, 'expira_at' => $expira];
    }
}
