<?php

namespace App\Services;

use App\Models\LeaveRequest;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    private const LAYOUT_DIR = 'views/pdf/layouts';

    public function render(LeaveRequest $solicitud): string
    {
        $html = $this->loadLayout($solicitud->tipo);
        $html = $this->replaceTokens($html, $solicitud);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('letter', 'landscape')
            ->setWarnings(false);

        return $pdf->output();
    }

    private function loadLayout(string $tipo): string
    {
        $path = resource_path(self::LAYOUT_DIR.'/'.$tipo.'.html');

        if (! file_exists($path)) {
            $path = resource_path(self::LAYOUT_DIR.'/permiso.html');
        }

        return file_get_contents($path);
    }

    private function replaceTokens(string $html, LeaveRequest $solicitud): string
    {
        $aprobacion = $solicitud->aprobaciones()
            ->where('accion', 'aprobado')
            ->latest('orden')
            ->first();

        $nombreAprobador = $solicitud->autorizador?->nombre_completo ?? '—';
        $fechaAprobacion = $solicitud->fecha_aprobacion?->format('d/m/Y') ?? '—';

        if ($aprobacion && $aprobacion->credential_id) {
            $firmaDigital = substr($aprobacion->credential_id, 0, 16).'…';
        } elseif ($aprobacion?->fallback_password) {
            $firmaDigital = 'Aprobado con contraseña';
        } else {
            $firmaDigital = '—';
        }

        $firmaBpm = $solicitud->firma_bpm_trabajador ?? 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        $tokens = [
            '{{solicitante}}' => $solicitud->user->nombre_completo,
            '{{tipo}}' => $solicitud->tipo,
            '{{motivo}}' => $solicitud->motivo ?? '—',
            '{{jornada}}' => $solicitud->jornada === 'completa' ? 'Día completo' : 'Medio día',
            '{{fecha_desde}}' => $solicitud->fecha_desde->format('d/m/Y'),
            '{{fecha_hasta}}' => $solicitud->fecha_hasta->format('d/m/Y'),
            '{{hora_desde}}' => $solicitud->hora_desde?->format('H:i') ?? '',
            '{{hora_hasta}}' => $solicitud->hora_hasta?->format('H:i') ?? '',
            '{{dias}}' => (string) $solicitud->dias_habiles,
            '{{observaciones}}' => $solicitud->comentarios ?? '—',
            '{{firma_bpm}}' => $firmaBpm,
            '{{autorizado_por}}' => $nombreAprobador,
            '{{firma_digital}}' => $firmaDigital,
            '{{fecha_aprobacion}}' => $fechaAprobacion,
            '{{recibido_por}}' => $solicitud->receptor?->nombre_completo ?? '—',
            '{{fecha_recepcion}}' => $solicitud->fecha_recepcion?->format('d/m/Y') ?? '—',
            '{{anios_servicio}}' => (string) ($solicitud->anios_servicio ?? '—'),
            '{{dias_correspondientes}}' => (string) ($solicitud->dias_correspondientes ?? '—'),
            '{{fecha_limite_vacaciones}}' => $solicitud->fecha_limite_vacaciones?->format('d/m/Y') ?? '—',
            '{{check_jornada_completa}}' => $solicitud->jornada === 'completa' ? '✗' : '&nbsp;',
            '{{check_jornada_media}}' => $solicitud->jornada === 'media' ? '✗' : '&nbsp;',
        ];

        $checkboxMap = [
            'vacacion' => 'vacacion',
            'descuento_planilla' => 'descuento',
            'sin_afectar' => 'sin_afectar',
        ];

        foreach ($checkboxMap as $valor => $nombre) {
            $tokens["{{check_{$nombre}}}"] = $solicitud->considerado_como === $valor ? '✗' : '&nbsp;';
        }

        $tokens['{{c_permiso}}'] = $solicitud->tipo !== 'vacacion' ? '✗' : '&nbsp;';
        $tokens['{{c_completa}}'] = $solicitud->jornada === 'completa' ? '✗' : '&nbsp;';
        $tokens['{{c_media}}'] = $solicitud->jornada === 'media' ? '✗' : '&nbsp;';
        $tokens['{{c_vacacion}}'] = $solicitud->considerado_como === 'vacacion' ? '✗' : '&nbsp;';
        $tokens['{{c_descuento}}'] = $solicitud->considerado_como === 'descuento_planilla' ? '✗' : '&nbsp;';
        $tokens['{{c_sin_afectar}}'] = $solicitud->considerado_como === 'sin_afectar' ? '✗' : '&nbsp;';

        return str_replace(array_keys($tokens), array_values($tokens), $html);
    }
}
