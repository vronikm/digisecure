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
                    permisos: ['clientes.ver'],
                    description: 'Empresas a las que prestas servicios',
                    children: [
                        new MenuItem(
                            label: 'Listado de clientes',
                            icon: 'bi-list-ul',
                            url: '/clientes',
                            permisos: ['clientes.ver'],
                            description: 'Filtros, estados y contactos',
                        ),
                        new MenuItem(
                            label: 'Nuevo cliente',
                            icon: 'bi-plus-circle',
                            url: '/clientes/crear',
                            permisos: ['clientes.crear'],
                            description: 'Registrar nuevo cliente',
                        ),
                    ],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Puestos / Sitios',
                    icon: 'bi-geo-alt',
                    permisos: ['puestos.ver'],
                    description: 'Ubicaciones donde se presta servicio',
                    children: [
                        new MenuItem(
                            label: 'Listado de puestos',
                            icon: 'bi-list-ul',
                            url: '/puestos',
                            permisos: ['puestos.ver'],
                            description: 'Sitios con plazas y modalidad',
                        ),
                        new MenuItem(
                            label: 'Nuevo puesto',
                            icon: 'bi-plus-circle',
                            url: '/puestos/crear',
                            permisos: ['puestos.crear'],
                            description: 'Registrar sitio asociado a un cliente',
                        ),
                    ],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Guardias',
                    icon: 'bi-person-badge',
                    permisos: ['guardias.ver'],
                    description: 'Altas, fichas y seguimiento del personal',
                    children: [
                        new MenuItem(
                            label: 'Listado de guardias',
                            icon: 'bi-list-ul',
                            url: '/guardias',
                            permisos: ['guardias.ver'],
                            description: 'Datos personales y acceso al historial de fichas',
                        ),
                        new MenuItem(
                            label: 'Nueva ficha',
                            icon: 'bi-person-plus',
                            url: '/guardias/crear',
                            permisos: ['guardias.crear'],
                            description: 'Registrar un nuevo guardia',
                        ),
                    ],
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
                    permisos: ['asignaciones.ver'],
                    description: 'Guardias asignados a puestos',
                    children: [
                        new MenuItem(
                            label: 'Listado',
                            icon: 'bi-list-ul',
                            url: '/asignaciones',
                            permisos: ['asignaciones.ver'],
                            description: 'Cobertura, periodos y estado',
                        ),
                        new MenuItem(
                            label: 'Nueva asignacion',
                            icon: 'bi-plus-circle',
                            url: '/asignaciones/crear',
                            permisos: ['asignaciones.crear'],
                            description: 'Vincular guardia a puesto',
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
                    permisos: ['usuarios.ver'],
                    description: 'Cuentas, estados y accesos internos',
                    children: [
                        new MenuItem(
                            label: 'Listado',
                            icon: 'bi-person-lines-fill',
                            url: '/usuarios',
                            permisos: ['usuarios.ver'],
                            description: 'Administrar cuentas activas',
                        ),
                        new MenuItem(
                            label: 'Crear usuario',
                            icon: 'bi-person-plus-fill',
                            url: '/usuarios/crear',
                            permisos: ['usuarios.crear'],
                            description: 'Dar alta a un nuevo usuario',
                        ),
                    ],
                ),
            ],
            [
                'type' => 'item',
                'item' => new MenuItem(
                    label: 'Roles y Permisos',
                    icon: 'bi-shield-lock',
                    permisos: ['roles.ver'],
                    description: 'Perfiles, permisos y alcance por rol',
                    children: [
                        new MenuItem(
                            label: 'Listado de roles',
                            icon: 'bi-list-ul',
                            url: '/roles',
                            permisos: ['roles.ver'],
                            description: 'Revisar perfiles disponibles',
                        ),
                        new MenuItem(
                            label: 'Crear rol',
                            icon: 'bi-plus-circle',
                            url: '/roles/crear',
                            permisos: ['roles.crear'],
                            description: 'Definir un nuevo perfil',
                        ),
                    ],
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
