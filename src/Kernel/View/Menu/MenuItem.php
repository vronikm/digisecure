<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\View\Menu;

/**
 * Item de menú del sidebar.
 *
 * Cada item puede tener hijos (submenú). La visibilidad depende de los
 * permisos del usuario: si el usuario no tiene ninguno de los permisos
 * requeridos, el item no se renderiza.
 */
final readonly class MenuItem
{
    /**
     * @param string $label          Texto visible
     * @param string $icon           Clase de icono Bootstrap Icons (ej: 'bi-speedometer2')
     * @param string|null $url       URL de destino (null si es solo contenedor)
     * @param list<string> $permisos Permisos requeridos (al menos uno). Vacío = siempre visible.
     * @param list<MenuItem> $children  Hijos del submenú
     * @param string|null $badge     Texto del badge (ej: conteo de pendientes)
     * @param string $badgeClass     Clase CSS del badge
     * @param string|null $description Texto de apoyo para clarificar el objetivo del item
     */
    public function __construct(
        public string $label,
        public string $icon,
        public ?string $url = null,
        public array $permisos = [],
        public array $children = [],
        public ?string $badge = null,
        public string $badgeClass = 'bg-primary',
        public ?string $description = null,
    ) {
    }

    /**
     * ¿El usuario puede ver este item?
     *
     * @param list<string> $permisosUsuario
     */
    public function isVisibleFor(array $permisosUsuario): bool
    {
        // Sin permisos requeridos → siempre visible
        if ($this->permisos === []) {
            return true;
        }

        // Al menos un permiso del item debe coincidir
        foreach ($this->permisos as $p) {
            if (in_array($p, $permisosUsuario, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿Tiene al menos un hijo visible?
     *
     * @param list<string> $permisosUsuario
     */
    public function hasVisibleChildren(array $permisosUsuario): bool
    {
        foreach ($this->children as $child) {
            if ($child->isVisibleFor($permisosUsuario)) {
                return true;
            }
        }
        return false;
    }

    /**
     * ¿Es el item activo para la ruta actual?
     */
    public function isActive(string $currentPath): bool
    {
        if ($this->url !== null && $this->url === $currentPath) {
            return true;
        }

        foreach ($this->children as $child) {
            if ($child->isActive($currentPath)) {
                return true;
            }
        }

        return false;
    }
}
