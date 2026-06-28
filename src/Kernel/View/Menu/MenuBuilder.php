<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\View\Menu;

/**
 * Construye la estructura de menu del sidebar.
 *
 * El menu se define aqui de forma centralizada. Los items se filtran
 * por permisos del usuario al momento de renderizar.
 */
final class MenuBuilder
{
    /**
     * Retorna la definicion completa del menu del sistema.
     *
     * @return list<array{type: string, label?: string, item?: MenuItem}>
     */
    public static function build(): array
    {
        return [
            ['type' => 'header', 'label' => 'Inicio'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Dashboard',
                    icon: 'bi-speedometer2',
                    url: '/dashboard',
                    description: 'Resumen operativo y alertas clave',
                ),
            ],

            ['type' => 'header', 'label' => 'Operacion'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Clientes',
                    icon: 'bi-briefcase',
                    url: '/clientes',
                    permisos: ['clientes.ver'],
                    description: 'Clientes a las que se presta servicios',                    
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Puestos / Sitios',
                    icon: 'bi-geo-alt',
                    url: '/puestos',
                    permisos: ['puestos.ver'],
                    description: 'Ubicaciones donde se presta servicio',
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Guardias',
                    icon: 'bi-person-badge',
                    url: '/guardias',
                    permisos: ['guardias.ver'],
                    description: 'Altas, fichas y seguimiento del personal',                    
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Reclutamiento',
                    icon: 'bi-person-plus-fill',
                    url: '/reclutamiento',
                    permisos: ['aspirantes.gestionar'],
                    description: 'Aspirantes: revisión y aprobación de postulantes',
                    activeWhenPrefixes: ['/reclutamiento/'],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Asignaciones',
                    icon: 'bi-link-45deg',
                    url: '/asignaciones',
                    permisos: ['asignaciones.ver'],
                    description: 'Guardias asignados a puestos',                    
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Expedientes',
                    icon: 'bi-folder2-open',
                    permisos: ['expedientes.ver'],
                    description: 'Control documental y validaciones',
                    children: [
                        new MenuItem(
                            label: 'Todos los expedientes',
                            icon: 'bi-folder',
                            url: '/expedientes',
                            permisos: ['expedientes.ver'],
                            description: 'Estado general de cada expediente',
                        ),
                        new MenuItem(
                            label: 'Pendientes de validacion',
                            icon: 'bi-clipboard-check',
                            url: '/expedientes/pendientes',
                            permisos: ['expedientes.validar'],
                            description: 'Documentos listos para revisar',
                        ),
                    ],
                ),
            ],

            ['type' => 'header', 'label' => 'Control'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'KPIs y Reportes',
                    icon: 'bi-graph-up',
                    permisos: ['reportes.ver'],
                    description: 'Indicadores, visualizacion y exportacion',
                    children: [
                        new MenuItem(
                            label: 'Dashboard KPIs',
                            icon: 'bi-bar-chart-line',
                            url: '/reportes/kpis',
                            permisos: ['reportes.ver'],
                            description: 'Vista ejecutiva de rendimiento',
                        ),
                        new MenuItem(
                            label: 'Exportar',
                            icon: 'bi-file-earmark-arrow-down',
                            url: '/reportes/exportar',
                            permisos: ['reportes.exportar'],
                            description: 'Descargar datos y reportes',
                        ),
                    ],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Importaciones',
                    icon: 'bi-file-earmark-spreadsheet',
                    permisos: ['importaciones.gemeseg'],
                    description: 'Carga controlada de archivos operativos',
                    children: [
                        new MenuItem(
                            label: 'Excel GEMESEG',
                            icon: 'bi-upload',
                            url: '/importaciones/gemeseg',
                            permisos: ['importaciones.gemeseg'],
                            description: 'Analizar y guardar staging cifrado',
                        ),
                    ],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Auditoria',
                    icon: 'bi-journal-text',
                    url: '/auditoria',
                    permisos: ['auditoria.ver'],
                    description: 'Trazabilidad de eventos y accesos',
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Notificaciones',
                    icon: 'bi-bell',
                    url: '/notificaciones',
                    permisos: ['notificaciones.ver'],
                    description: 'Mensajes programados y seguimiento',
                ),
            ],

            ['type' => 'header', 'label' => 'Configuracion'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Usuarios',
                    icon: 'bi-people',
                    url: '/usuarios',
                    permisos: ['usuarios.ver'],
                    description: 'Cuentas, estados y accesos internos',                   
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Roles y Permisos',
                    icon: 'bi-shield-lock',
                    url: '/roles',
                    permisos: ['roles.ver'],
                    description: 'Perfiles, permisos y alcance por rol',
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Empresa',
                    icon: 'bi-buildings',
                    permisos: [],
                    description: 'Gestion de la empresa actual',
                    children: [
                        new MenuItem(
                            label: 'Mi empresa',
                            icon: 'bi-building',
                            url: '/empresa',
                            permisos: ['tenants.ver'],
                            description: 'Datos del tenant actual',
                        ),
                        new MenuItem(
                            label: 'Editar mi empresa',
                            icon: 'bi-pencil-square',
                            url: '/empresa/editar',
                            permisos: ['tenants.editar'],
                            description: 'Actualizar informacion del tenant actual',
                        ),
                    ],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Suscripcion',
                    icon: 'bi-gem',
                    url: '/suscripcion',
                    permisos: ['suscripcion.ver'],
                    description: 'Plan contratado, uso y renovaciones',
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Tipos de documento',
                    icon: 'bi-file-earmark-ruled',
                    url: '/configuracion/documentos',
                    permisos: ['configuracion.documentos'],
                    description: 'Catalogo documental y vigencias',
                ),
            ],

            ['type' => 'header', 'label' => 'Privacidad'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'LOPDP / Datos Personales',
                    icon: 'bi-file-earmark-lock',
                    permisos: ['lopdp.gestionar'],
                    description: 'Consentimientos y solicitudes ARCO-P',
                    children: [
                        new MenuItem(
                            label: 'Panel LOPDP',
                            icon: 'bi-speedometer2',
                            url: '/lopdp',
                            permisos: ['lopdp.gestionar'],
                            description: 'Resumen de cumplimiento',
                        ),
                        new MenuItem(
                            label: 'Solicitudes ARCO-P',
                            icon: 'bi-list-check',
                            url: '/lopdp/solicitudes',
                            permisos: ['lopdp.gestionar'],
                            description: 'Atencion de requerimientos',
                        ),
                        new MenuItem(
                            label: 'Consentimientos',
                            icon: 'bi-shield-check',
                            url: '/lopdp/consentimientos',
                            permisos: ['lopdp.gestionar'],
                            description: 'Control y revocacion de consentimientos',
                            activeWhenPrefixes: ['/lopdp/consentimientos/'],
                        ),
                    ],
                ),
            ],

            ['type' => 'header', 'label' => 'Plataforma'],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Panel Superadmin',
                    icon: 'bi-grid-1x2-fill',
                    permisos: ['superadmin.panel'],
                    description: 'Gestion global de la plataforma',
                    children: [
                        new MenuItem(
                            label: 'Dashboard global',
                            icon: 'bi-speedometer',
                            url: '/admin',
                            permisos: ['superadmin.panel'],
                            description: 'Metricas y KPIs del operador',
                        ),
                        new MenuItem(
                            label: 'Planes',
                            icon: 'bi-gem',
                            url: '/admin/planes',
                            permisos: ['superadmin.planes.ver'],
                            description: 'Catalogo y configuracion de planes SaaS',
                        ),
                        new MenuItem(
                            label: 'Empresas',
                            icon: 'bi-buildings',
                            url: '/admin/tenants',
                            permisos: ['superadmin.tenants.ver'],
                            description: 'Listado y gestion de tenants',
                            activeWhenPrefixes: ['/admin/tenants/'],
                        ),
                        new MenuItem(
                            label: 'Log de registros',
                            icon: 'bi-journal-text',
                            url: '/admin/registros',
                            permisos: ['superadmin.registros.ver'],
                            description: 'Intentos de auto-registro',
                        ),
                    ],
                ),
            ],
        ];
    }
}
