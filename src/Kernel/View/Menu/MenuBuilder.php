<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\View\Menu;

/**
 * Construye la estructura de menú del sidebar.
 *
 * El menú se define aquí de forma centralizada. Los items se filtran
 * por permisos del usuario al momento de renderizar.
 *
 * Estructura jerárquica:
 *   - Header (separador visual)
 *   - Item con URL (hoja)
 *   - Item con hijos (submenú desplegable)
 */
final class MenuBuilder
{
    /**
     * Retorna la definición completa del menú del sistema.
     *
     * @return list<array{type: string, label?: string, item?: MenuItem}>
     */
    public static function build(): array
    {
        return [
            // --- Principal ---
            ['type' => 'header', 'label' => 'PRINCIPAL'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Dashboard',
                    icon: 'bi-speedometer2',
                    url: '/dashboard',
                ),
            ],

            // --- Gestión de Guardias ---
            ['type' => 'header', 'label' => 'GESTIÓN'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Guardias',
                    icon: 'bi-person-badge',
                    permisos: ['guardias.ver'],
                    children: [
                        new MenuItem(
                            label: 'Listado',
                            icon: 'bi-list-ul',
                            url: '/guardias',
                            permisos: ['guardias.ver'],
                        ),
                        new MenuItem(
                            label: 'Nueva ficha',
                            icon: 'bi-person-plus',
                            url: '/guardias/crear',
                            permisos: ['guardias.crear'],
                        ),
                    ],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Expedientes',
                    icon: 'bi-folder2-open',
                    permisos: ['expedientes.ver'],
                    children: [
                        new MenuItem(
                            label: 'Todos los expedientes',
                            icon: 'bi-folder',
                            url: '/expedientes',
                            permisos: ['expedientes.ver'],
                        ),
                        new MenuItem(
                            label: 'Pendientes de validación',
                            icon: 'bi-clipboard-check',
                            url: '/expedientes/pendientes',
                            permisos: ['expedientes.validar'],
                        ),
                    ],
                ),
            ],

            // --- Reportes ---
            ['type' => 'header', 'label' => 'REPORTES'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'KPIs y Reportes',
                    icon: 'bi-graph-up',
                    permisos: ['reportes.ver'],
                    children: [
                        new MenuItem(
                            label: 'Dashboard KPIs',
                            icon: 'bi-bar-chart-line',
                            url: '/reportes/kpis',
                            permisos: ['reportes.ver'],
                        ),
                        new MenuItem(
                            label: 'Exportar',
                            icon: 'bi-file-earmark-arrow-down',
                            url: '/reportes/exportar',
                            permisos: ['reportes.exportar'],
                        ),
                    ],
                ),
            ],

            // --- Administración ---
            ['type' => 'header', 'label' => 'ADMINISTRACIÓN'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Usuarios',
                    icon: 'bi-people',
                    permisos: ['usuarios.ver'],
                    children: [
                        new MenuItem(
                            label: 'Listado',
                            icon: 'bi-person-lines-fill',
                            url: '/usuarios',
                            permisos: ['usuarios.ver'],
                        ),
                        new MenuItem(
                            label: 'Crear usuario',
                            icon: 'bi-person-plus-fill',
                            url: '/usuarios/crear',
                            permisos: ['usuarios.crear'],
                        ),
                    ],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Roles y Permisos',
                    icon: 'bi-shield-lock',
                    url: '/roles',
                    permisos: ['roles.ver'],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Auditoría',
                    icon: 'bi-journal-text',
                    url: '/auditoria',
                    permisos: ['auditoria.ver'],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Empresa',
                    icon: 'bi-building',
                    url: '/empresa',
                    permisos: ['tenants.ver'],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'LOPDP / Datos Personales',
                    icon: 'bi-file-earmark-lock',
                    url: '/lopdp',
                    permisos: ['lopdp.gestionar'],
                ),
            ],
        ];
    }
}
