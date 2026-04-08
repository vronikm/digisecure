<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\View;

use DigiSecure\Kernel\Http\Response;

/**
 * Motor de renderizado de vistas PHP planas.
 *
 * Convenciones:
 *  - Las vistas viven en templates/ con extensión .php
 *  - Toda variable se pasa explícitamente vía $data
 *  - El helper e() escapa HTML (ENT_QUOTES | ENT_HTML5, UTF-8)
 *  - Las vistas pueden extender un layout usando $this->layout()
 *  - Las secciones permiten inyectar contenido en el layout
 *
 * REGLA DE ORO: NUNCA hacer echo de datos sin escapar. Usar e($var) siempre.
 */
final class ViewRenderer
{
    private string $templatesPath;

    /** @var array<string, string> Secciones definidas por la vista actual */
    private array $sections = [];

    /** @var string|null Sección actualmente siendo capturada */
    private ?string $currentSection = null;

    /** @var string|null Layout que la vista solicita */
    private ?string $layoutFile = null;

    public function __construct(string $templatesPath)
    {
        $this->templatesPath = rtrim($templatesPath, '/\\');
    }

    /**
     * Renderiza una vista y devuelve el HTML completo.
     *
     * @param string $view  Ruta relativa sin extensión (ej: 'auth/login', 'dashboard/index')
     * @param array<string, mixed> $data  Variables disponibles en la vista
     */
    public function render(string $view, array $data = []): string
    {
        $this->sections = [];
        $this->currentSection = null;
        $this->layoutFile = null;

        // Renderizar la vista hija
        $viewContent = $this->renderFile($view, $data);

        // Si la vista solicitó un layout, renderizar el layout
        if ($this->layoutFile !== null) {
            // El contenido de la vista va en la sección 'content' por defecto
            if (!isset($this->sections['content'])) {
                $this->sections['content'] = $viewContent;
            }

            $layoutData = array_merge($data, ['_sections' => $this->sections]);
            $viewContent = $this->renderFile($this->layoutFile, $layoutData);
        }

        return $viewContent;
    }

    /**
     * Renderiza y devuelve un Response HTTP.
     *
     * @param array<string, mixed> $data
     */
    public function response(string $view, array $data = [], int $status = 200): Response
    {
        return Response::html($this->render($view, $data), $status);
    }

    /**
     * Renderiza un archivo de template y devuelve su contenido.
     *
     * @param array<string, mixed> $__data
     */
    private function renderFile(string $__view, array $__data): string
    {
        $__file = $this->resolveViewPath($__view);

        if (!is_file($__file)) {
            throw new \RuntimeException("Vista no encontrada: {$__view} (buscado en: {$__file})");
        }

        // Extraer datos como variables locales
        extract($__data, EXTR_SKIP);

        // Hacer disponible $this (ViewRenderer) para llamar a layout(), section(), yield()
        $view = $this;

        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean() ?: '';
    }

    /**
     * Llamado desde una vista para indicar que extiende un layout.
     *
     * @param string $layout  Ruta del layout (ej: 'layout/app')
     */
    public function layout(string $layout): void
    {
        $this->layoutFile = $layout;
    }

    /**
     * Inicia la captura de una sección con nombre.
     * Debe cerrarse con endSection().
     */
    public function section(string $name): void
    {
        if ($this->currentSection !== null) {
            throw new \RuntimeException("Sección '{$this->currentSection}' no cerrada antes de abrir '{$name}'.");
        }
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * Cierra la sección actual y almacena su contenido.
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new \RuntimeException('endSection() llamado sin section() previo.');
        }
        $this->sections[$this->currentSection] = ob_get_clean() ?: '';
        $this->currentSection = null;
    }

    /**
     * Llamado desde el layout para imprimir el contenido de una sección.
     *
     * @param string $name     Nombre de la sección
     * @param string $default  Contenido por defecto si la sección no fue definida
     */
    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Renderiza un partial (fragmento reutilizable).
     *
     * @param string $partial  Ruta del partial (ej: 'partials/alert')
     * @param array<string, mixed> $data
     */
    public function include(string $partial, array $data = []): string
    {
        return $this->renderFile($partial, $data);
    }

    /**
     * Helper de escape HTML. Atajo desde las vistas.
     */
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function resolveViewPath(string $view): string
    {
        // Normalizar separadores
        $view = str_replace('.', '/', $view);
        return "{$this->templatesPath}/{$view}.php";
    }
}
