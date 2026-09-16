<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Infrastructure\Persistence;

use DigiSecure\Infrastructure\Persistence\Migration;
use DigiSecure\Infrastructure\Persistence\MigrationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase
{
    private const SQL_LF = "-- Migration: prueba\n-- @up\nCREATE TABLE t (id INT);\n-- @down\nDROP TABLE t;\n";

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ds_migration_test_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/lf', 0777, true);
        mkdir($this->dir . '/crlf', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*.sql') ?: [] as $file) {
            unlink($file);
        }
        foreach (['lf', 'crlf'] as $sub) {
            @rmdir($this->dir . '/' . $sub);
        }
        @rmdir($this->dir);
    }

    #[Test]
    public function checksum_es_identico_con_finales_de_linea_lf_y_crlf(): void
    {
        // Git en Windows (core.autocrlf=true) entrega CRLF; Linux/produccion, LF.
        $lf = $this->write('lf', '001_prueba.sql', self::SQL_LF);
        $crlf = $this->write('crlf', '001_prueba.sql', str_replace("\n", "\r\n", self::SQL_LF));

        $a = Migration::fromFile($lf);
        $b = Migration::fromFile($crlf);

        self::assertSame(hash('sha256', self::SQL_LF), $a->checksum);
        self::assertSame($a->checksum, $b->checksum);
        self::assertSame($a->upSql, $b->upSql);
        self::assertSame($a->downSql, $b->downSql);
    }

    #[Test]
    public function separa_secciones_up_y_down(): void
    {
        $m = Migration::fromFile($this->write('lf', '007_tabla_t.sql', self::SQL_LF));

        self::assertSame('007', $m->version);
        self::assertSame('tabla_t', $m->name);
        self::assertSame('CREATE TABLE t (id INT);', $m->upSql);
        self::assertSame('DROP TABLE t;', $m->downSql);
    }

    #[Test]
    public function rechaza_nombre_de_archivo_invalido(): void
    {
        $this->expectException(MigrationException::class);
        Migration::fromFile($this->write('lf', 'sin_version.sql', self::SQL_LF));
    }

    private function write(string $sub, string $name, string $contents): string
    {
        $path = $this->dir . '/' . $sub . '/' . $name;
        file_put_contents($path, $contents);
        return $path;
    }
}
