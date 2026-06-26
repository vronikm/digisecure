<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Aspirante;

use DateTimeImmutable;
use DateTimeZone;
use DigiSecure\Aspirante\Application\RegistrarAspiranteService;
use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Security\Crypto\Hasher;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegistrarAspiranteServiceTest extends TestCase
{
    private FakeAspiranteRepository $repo;
    private RegistrarAspiranteService $service;

    protected function setUp(): void
    {
        // Reloj fijo: la edad de los aspirantes se vuelve determinista.
        Clock::freeze(new DateTimeImmutable('2026-06-26 12:00:00', new DateTimeZone('UTC')));
        $this->repo = new FakeAspiranteRepository();
        // Hasher con coste bajo: tests rapidos (no afecta la logica validada).
        $this->service = new RegistrarAspiranteService($this->repo, new Hasher(8192, 1, 1));
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
    }

    /**
     * @return array<string, mixed>
     */
    private function entradaValida(array $overrides = []): array
    {
        return array_merge([
            'tipo_documento'   => 'cedula',
            'documento'        => '1710034065', // cédula ecuatoriana válida (mód. 10)
            'apellidos'        => 'Pérez Gómez',
            'nombres'          => 'Juan Carlos',
            'celular'          => '0991234567',
            'correo'           => 'juan@example.com',
            'estatura'         => '1.75',
            'fecha_nacimiento' => '2000-01-15',
            'password'              => 'Segura123',
            'password_confirmacion' => 'Segura123',
            'acepta_politicas' => true,
        ], $overrides);
    }

    #[Test]
    public function registra_un_aspirante_valido_y_normaliza_los_datos(): void
    {
        $resultado = $this->service->execute($this->entradaValida(), '200.1.2.3', 'PHPUnit');

        self::assertCount(1, $this->repo->creados);
        $fila = $this->repo->creados[0];

        self::assertSame('cedula', $fila['tipo_documento']);
        self::assertSame('1710034065', $fila['documento']);
        self::assertSame(175, $fila['estatura_cm']);
        self::assertSame('0991234567', $fila['celular']);
        self::assertSame('juan@example.com', $fila['correo_normalizado']);
        self::assertSame('Juan Carlos Pérez Gómez', $resultado['nombre_completo']);
        self::assertNotSame('', $resultado['id']);
        self::assertNotEmpty($fila['verificacion_token_hash']);
        self::assertArrayHasKey('verificacion_token', $resultado);
    }

    #[Test]
    public function normaliza_celular_con_prefijo_internacional(): void
    {
        $this->service->execute($this->entradaValida(['celular' => '+593 99 123 4567']), '200.1.2.3', 'PHPUnit');

        self::assertSame('0991234567', $this->repo->creados[0]['celular']);
    }

    #[Test]
    public function acepta_estatura_en_centimetros(): void
    {
        $this->service->execute($this->entradaValida(['estatura' => '178']), '200.1.2.3', 'PHPUnit');

        self::assertSame(178, $this->repo->creados[0]['estatura_cm']);
    }

    #[Test]
    public function rechaza_cedula_invalida(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['documento' => '1234567890']), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_tipo_de_documento_desconocido(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['tipo_documento' => 'licencia']), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_menor_de_edad(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['fecha_nacimiento' => '2015-01-01']), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_estatura_fuera_de_rango(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['estatura' => '0.80']), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_celular_no_ecuatoriano(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['celular' => '1234567']), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_si_no_acepta_politicas(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['acepta_politicas' => false]), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_documento_duplicado(): void
    {
        $this->repo->documentos[] = '1710034065';

        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_correo_duplicado(): void
    {
        $this->repo->correos[] = 'juan@example.com';

        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function aplica_rate_limit_por_ip(): void
    {
        $this->repo->intentos = 8;

        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function acepta_pasaporte_alfanumerico(): void
    {
        $this->service->execute(
            $this->entradaValida(['tipo_documento' => 'pasaporte', 'documento' => 'ab123456']),
            '200.1.2.3',
            'PHPUnit',
        );

        self::assertSame('AB123456', $this->repo->creados[0]['documento']);
    }

    #[Test]
    public function guarda_hash_argon2_de_la_password(): void
    {
        $this->service->execute($this->entradaValida(), '200.1.2.3', 'PHPUnit');

        self::assertArrayHasKey('password_hash', $this->repo->creados[0]);
        self::assertStringStartsWith('$argon2', $this->repo->creados[0]['password_hash']);
    }

    #[Test]
    public function rechaza_password_corta(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['password' => 'Ab1', 'password_confirmacion' => 'Ab1']), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_password_sin_mayuscula(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['password' => 'segura123', 'password_confirmacion' => 'segura123']), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_password_sin_numero(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['password' => 'SeguraClave', 'password_confirmacion' => 'SeguraClave']), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_passwords_que_no_coinciden(): void
    {
        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(['password' => 'Segura123', 'password_confirmacion' => 'Otra123']), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function genera_token_de_verificacion_de_email(): void
    {
        $r = $this->service->execute($this->entradaValida(), '200.1.2.3', 'PHPUnit');

        self::assertSame(64, strlen($r['verificacion_token']));                               // 32 bytes hex
        self::assertSame(64, strlen($this->repo->creados[0]['verificacion_token_hash']));     // sha256 hex
        self::assertNotSame($r['verificacion_token'], $this->repo->creados[0]['verificacion_token_hash']);
        self::assertSame(hash('sha256', $r['verificacion_token']), $this->repo->creados[0]['verificacion_token_hash']);
    }

    #[Test]
    public function rechaza_correo_ya_registrado_como_usuario(): void
    {
        $this->repo->correosUsuarios[] = 'juan@example.com';

        $this->expectException(DomainException::class);
        $this->service->execute($this->entradaValida(), '200.1.2.3', 'PHPUnit');
    }

    #[Test]
    public function rechaza_dominio_en_lista_negra(): void
    {
        $service = new RegistrarAspiranteService($this->repo, new Hasher(8192, 1, 1), ['tempmail.com']);

        $this->expectException(DomainException::class);
        $service->execute($this->entradaValida(['correo' => 'bot@tempmail.com']), '200.1.2.3', 'PHPUnit');
    }
}

/**
 * Doble de prueba en memoria del AspiranteRepository: omite el constructor real
 * (que requiere PdoConnection) y captura lo que el servicio intenta persistir.
 */
final class FakeAspiranteRepository extends AspiranteRepository
{
    /** @var list<array<string, mixed>> */
    public array $creados = [];
    /** @var list<string> */
    public array $documentos = [];
    /** @var list<string> */
    public array $correos = [];
    /** @var list<string> */
    public array $correosUsuarios = [];
    public int $intentos = 0;

    public function __construct()
    {
        // Intencionalmente NO llamamos al constructor padre (sin DB en tests).
    }

    public function crear(array $a): void
    {
        $this->creados[] = $a;
    }

    public function existeDocumento(string $documento): bool
    {
        return in_array($documento, $this->documentos, true);
    }

    public function existeCorreo(string $correoNormalizado): bool
    {
        return in_array($correoNormalizado, $this->correos, true);
    }

    public function existeCorreoEnUsuarios(string $correoNormalizado): bool
    {
        return in_array($correoNormalizado, $this->correosUsuarios, true);
    }

    public function contarIntentosRecientes(string $ip, int $ventanaMinutos = 60): int
    {
        return $this->intentos;
    }
}
