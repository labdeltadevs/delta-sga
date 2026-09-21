<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Holiday;
use App\Models\LeaveApproval;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestNotification;
use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SgaSeeder extends Seeder
{
    public function run(): void
    {
        $this->crearRolesYPermisos();
        $this->crearAreas();
        $this->crearUsuarios();
        $this->crearVacacionesConfiguracion();
        $this->crearFeriadosBolivia();
        $this->crearBalancesYPrueba();
    }

    protected function crearRolesYPermisos(): void
    {
        $permisos = [
            'areas.list', 'areas.create', 'areas.edit', 'areas.delete',
            'areas.assign-head', 'areas.assign-users',
            'usuarios.list', 'usuarios.create', 'usuarios.edit', 'usuarios.delete',
            'usuarios.view-profile', 'usuarios.assign-area', 'usuarios.assign-superior',
            'usuarios.manage-signature', 'usuarios.manage-roles',
            'solicitudes.list', 'solicitudes.create', 'solicitudes.edit-own',
            'solicitudes.approve', 'solicitudes.reject', 'solicitudes.view-all',
            'solicitudes.export', 'solicitudes.cancel',
            'vacaciones.config', 'vacaciones.balance-view', 'vacaciones.balance-edit',
            'roles.list', 'roles.create', 'roles.edit', 'roles.delete', 'roles.assign-permissions',
            'feriados.list', 'feriados.create', 'feriados.edit', 'feriados.delete',
            'plantillas.manage',
        ];

        foreach ($permisos as $permiso) {
            Permission::updateOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $superadmin = Role::updateOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadmin->syncPermissions(Permission::all());

        $admin = Role::updateOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'areas.list', 'areas.assign-head', 'areas.assign-users',
            'usuarios.list', 'usuarios.create', 'usuarios.edit',
            'usuarios.view-profile', 'usuarios.assign-area', 'usuarios.assign-superior',
            'usuarios.manage-signature', 'usuarios.manage-roles',
            'solicitudes.list', 'solicitudes.create', 'solicitudes.approve', 'solicitudes.reject',
            'solicitudes.view-all', 'solicitudes.export', 'solicitudes.cancel',
            'vacaciones.config', 'vacaciones.balance-view', 'vacaciones.balance-edit',
            'feriados.list',
            'plantillas.manage',
        ]);

        $supervisor = Role::updateOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisor->syncPermissions([
            'solicitudes.list', 'solicitudes.approve', 'solicitudes.reject',
            'solicitudes.view-all', 'solicitudes.cancel', 'vacaciones.balance-view',
        ]);

        $trabajador = Role::updateOrCreate(['name' => 'trabajador', 'guard_name' => 'web']);
        $trabajador->syncPermissions([
            'solicitudes.list', 'solicitudes.create', 'solicitudes.edit-own', 'solicitudes.cancel',
            'vacaciones.balance-view',
        ]);
    }

    protected function crearAreas(): void
    {
        $area = fn (string $nombre, ?Area $padre, string $tipo, int $orden = 0): Area => Area::updateOrCreate(
            ['nombre' => $nombre],
            [
                'codigo' => strtoupper(Str::limit(Str::slug($nombre, '_'), 15, '')),
                'parent_id' => $padre?->id,
                'tipo' => $tipo,
                'orden' => $orden,
                'activo' => true,
            ],
        );

        $administrativa = $area('Administrativa', null, 'administrativa', 1);
        $comercial = $area('Comercial', null, 'comercial', 2);
        $tic = $area('T.I.C.', null, 'tic', 3);
        $produccion = $area('Producción', null, 'produccion', 4);

        $area('Contabilidad', $administrativa, 'administrativa', 1);
        $area('RRHH', $administrativa, 'administrativa', 2);
        $area('Tesorería', $administrativa, 'administrativa', 3);
        $area('Servicios', $administrativa, 'administrativa', 4);
        $area('Logística y Almacenes', $administrativa, 'administrativa', 5);

        $gerenciasRegionales = $area('Gerencias Regionales', $comercial, 'gerencia_regional', 1);
        $area('Equipo de Ventas', $gerenciasRegionales, 'equipo_ventas', 1);

        $areasProduccion = $area('Áreas', $produccion, 'area_produccion', 1);
        $area('Microbiología', $areasProduccion, 'area_produccion', 1);
        $area('Sólidos', $areasProduccion, 'area_produccion', 2);
        $area('Semisólidos', $areasProduccion, 'area_produccion', 3);
        $area('Líquidos', $areasProduccion, 'area_produccion', 4);
        $area('Empaque', $areasProduccion, 'area_produccion', 5);
        $area('Investigación y Desarrollo', $produccion, 'area_produccion', 2);
    }

    protected function crearUsuarios(): void
    {
        $roles = [
            'superadmin' => Role::findByName('superadmin', 'web'),
            'admin' => Role::findByName('admin', 'web'),
            'supervisor' => Role::findByName('supervisor', 'web'),
            'trabajador' => Role::findByName('trabajador', 'web'),
        ];

        $superadmin = User::updateOrCreate(
            ['email' => 'superadmin@delta-sga.com'],
            [
                'name' => 'Super Admin SGA',
                'nombre1' => 'Super',
                'apellido1' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'fecha_contrato' => Carbon::create(2015, 1, 1),
                'genero' => 'M',
                'es_superadmin' => true,
            ],
        );
        $superadmin->assignRole($roles['superadmin']);
        $superadmin->area()->associate(Area::where('nombre', 'T.I.C.')->first());

        $jefes = [
            'Administrativa' => ['Pedro', 'Fernández', 'M', 'jefe.administrativa@delta-sga.com', 'admin'],
            'Comercial' => ['Carlos', 'Ramírez', 'M', 'jefe.comercial@delta-sga.com', 'admin'],
            'T.I.C.' => ['Ana', 'Vargas', 'F', 'jefe.tic@delta-sga.com', 'admin'],
            'Producción' => ['Luis', 'Torres', 'M', 'jefe.produccion@delta-sga.com', 'admin'],
            'Contabilidad' => ['Pedro', 'Fernández', 'M', 'jefe.contabilidad@delta-sga.com', 'supervisor'],
            'RRHH' => ['María', 'González', 'F', 'jefe.rrhh@delta-sga.com', 'admin'],
            'Tesorería' => ['Lucía', 'Mendoza', 'F', 'jefe.tesoreria@delta-sga.com', 'supervisor'],
            'Servicios' => ['Roberto', 'Castillo', 'M', 'jefe.servicios@delta-sga.com', 'supervisor'],
            'Logística y Almacenes' => ['Sandra', 'Quispe', 'F', 'jefe.logistica@delta-sga.com', 'supervisor'],
            'Gerencias Regionales' => ['Jorge', 'Salinas', 'M', 'jefe.gerencias@delta-sga.com', 'supervisor'],
            'Equipo de Ventas' => ['Paola', 'Rivera', 'F', 'jefe.ventas@delta-sga.com', 'supervisor'],
            'Áreas' => ['Diego', 'Mamani', 'M', 'jefe.areas@delta-sga.com', 'supervisor'],
            'Microbiología' => ['Carmen', 'Rojas', 'F', 'jefe.microbiologia@delta-sga.com', 'supervisor'],
            'Sólidos' => ['Victor', 'Gutiérrez', 'M', 'jefe.solidos@delta-sga.com', 'supervisor'],
            'Semisólidos' => ['Rosa', 'Cruz', 'F', 'jefe.semisolidos@delta-sga.com', 'supervisor'],
            'Líquidos' => ['Marco', 'Flores', 'M', 'jefe.liquidos@delta-sga.com', 'supervisor'],
            'Empaque' => ['Sonia', 'Aguilar', 'F', 'jefe.empaque@delta-sga.com', 'supervisor'],
            'Investigación y Desarrollo' => ['Hernán', 'Cabrera', 'M', 'jefe.idd@delta-sga.com', 'supervisor'],
        ];

        foreach ($jefes as $areaNombre => $datos) {
            [$nombre1, $apellido1, $genero, $email, $rol] = $datos;
            $area = Area::where('nombre', $areaNombre)->first();
            if (! $area) {
                continue;
            }
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "$nombre1 $apellido1",
                    'nombre1' => $nombre1,
                    'apellido1' => $apellido1,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'fecha_contrato' => Carbon::create(fake()->numberBetween(2016, 2022), fake()->numberBetween(1, 12), fake()->numberBetween(1, 28)),
                    'genero' => $genero,
                ],
            );
            $user->assignRole($roles[$rol]);
            $user->area()->associate($area)->save();
            $this->marcarJefe($user, $areaNombre);
            $area->update(['head_user_id' => $user->id]);
        }

        $trabajadores = [
            ['Juan', 'Carlos', 'Pérez', 'Gómez', 'M', 'trabajador@delta-sga.com', 'Microbiología', 'Carmen Rojas'],
            ['María', 'Fernanda', 'López', 'Castro', 'F', 'trabajador2@delta-sga.com', 'Contabilidad', 'Pedro Fernández'],
            ['José', 'Antonio', 'Mamani', 'Quisbert', 'M', 'trabajador3@delta-sga.com', 'Sólidos', 'Victor Gutiérrez'],
            ['Andrea', null, 'Salazar', 'Villarroel', 'F', 'trabajador4@delta-sga.com', 'Líquidos', 'Marco Flores'],
            ['Miguel', 'Ángel', 'Choque', 'Apaza', 'M', 'trabajador5@delta-sga.com', 'Equipo de Ventas', 'Paola Rivera'],
        ];

        foreach ($trabajadores as $datos) {
            [$nombre1, $nombre2, $apellido1, $apellido2, $genero, $email, $areaNombre, $superiorNombre] = $datos;
            $area = Area::where('nombre', $areaNombre)->first();
            $superior = User::where('name', 'like', "%$superiorNombre%")->first();

            $parts = array_filter([$nombre1, $nombre2, $apellido1, $apellido2]);
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => implode(' ', $parts),
                    'nombre1' => $nombre1,
                    'nombre2' => $nombre2,
                    'apellido1' => $apellido1,
                    'apellido2' => $apellido2,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'fecha_contrato' => Carbon::create(fake()->numberBetween(2019, 2024), fake()->numberBetween(1, 12), fake()->numberBetween(1, 28)),
                    'genero' => $genero,
                    'area_id' => $area?->id,
                    'superior_id' => $superior?->id,
                ],
            );
            $user->assignRole($roles['trabajador']);
        }
    }

    protected function marcarJefe(User $user, string $areaNombre): void
    {
        $area = Area::where('nombre', $areaNombre)->first();
        if (! $area) {
            return;
        }
        $user->areas()->syncWithoutDetaching([$area->id => ['es_jefe' => true]]);
    }

    protected function crearVacacionesConfiguracion(): void
    {
        $anioActual = now()->year;
        foreach (range($anioActual - 1, $anioActual + 1) as $anio) {
            VacationConfig::updateOrCreate(
                ['gestion' => $anio],
                [
                    'dias_por_ley' => 15,
                    'fecha_limite_toma' => Carbon::create($anio + 1, 3, 31),
                    'activo' => true,
                ],
            );
        }
    }

    protected function crearFeriadosBolivia(): void
    {
        $feriados = [
            ['Año Nuevo', '01-01', 'nacional', true],
            ['Día del Estado Plurinacional', '01-22', 'nacional', true],
            ['Día del Trabajo', '05-01', 'nacional', true],
            ['Día de la Patria', '08-06', 'nacional', true],
            ['Todos los Santos', '11-02', 'nacional', true],
            ['Navidad', '12-25', 'nacional', true],
            ['Cierre de fin de año DELTA', '12-31', 'empresa', false],
        ];

        foreach ($feriados as [$nombre, $fechaStr, $tipo, $recurrente]) {
            [$mes, $dia] = array_map('intval', explode('-', $fechaStr));
            Holiday::updateOrCreate(
                ['nombre' => $nombre, 'fecha' => Carbon::create(now()->year, $mes, $dia)],
                ['tipo' => $tipo, 'recurrente_anual' => $recurrente, 'activo' => true],
            );
        }
    }

    protected function crearBalancesYPrueba(): void
    {
        $trabajador = User::where('email', 'trabajador@delta-sga.com')->first();
        $superior = $trabajador?->superior;

        if (! $trabajador || ! $superior) {
            return;
        }

        $anioActual = now()->year;
        $aniosServicio = $trabajador->anios_servicio;
        $diasTotales = $aniosServicio * 15;

        VacationBalance::updateOrCreate(
            ['user_id' => $trabajador->id, 'gestion' => $anioActual],
            [
                'anios_servicio' => $aniosServicio,
                'dias_totales' => $diasTotales,
                'dias_tomados' => 0,
                'dias_pendientes' => $diasTotales,
                'fecha_limite_toma' => VacationConfig::getForGestion($anioActual)?->fecha_limite_toma,
            ],
        );

        $solicitud = LeaveRequest::updateOrCreate(
            ['id' => 1],
            [
                'user_id' => $trabajador->id,
                'tipo' => 'vacacion',
                'motivo' => 'Vacaciones anuales',
                'fecha_desde' => Carbon::create($anioActual, 12, 15),
                'fecha_hasta' => Carbon::create($anioActual, 12, 19),
                'jornada' => 'completa',
                'considerado_como' => 'vacacion',
                'comentarios' => 'Solicitud de vacaciones de prueba.',
                'estado' => 'aprobado',
                'dias_totales' => 5,
                'dias_habiles' => 5,
                'anios_servicio' => $aniosServicio,
                'dias_correspondientes' => $diasTotales,
                'fecha_limite_vacaciones' => VacationConfig::getForGestion($anioActual)?->fecha_limite_toma,
                'autorizado_por' => $superior->id,
                'fecha_aprobacion' => now(),
                'recibido_por' => $superior->id,
                'fecha_recepcion' => now(),
            ],
        );

        LeaveApproval::updateOrCreate(
            ['leave_request_id' => $solicitud->id, 'orden' => 1],
            [
                'aprobador_id' => $superior->id,
                'accion' => 'aprobado',
                'comentario' => 'Aprobado por el inmediato superior.',
                'fecha_accion' => now(),
            ],
        );

        LeaveRequestNotification::create([
            'leave_request_id' => $solicitud->id,
            'user_id' => $trabajador->id,
            'tipo' => 'aprobada',
            'mensaje' => 'Tu solicitud de vacaciones fue aprobada.',
            'leida' => true,
        ]);
    }
}
