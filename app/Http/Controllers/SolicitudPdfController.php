<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\PdfService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class SolicitudPdfController extends Controller
{
    public function __invoke(LeaveRequest $solicitud, PdfService $pdf): Response
    {
        $user = auth()->user();
        $esDueno = $solicitud->user_id === $user->id;
        $esAprobador = $solicitud->aprobaciones()->where('aprobador_id', $user->id)->exists();
        $esAdmin = $user->es_superadmin || $user->hasRole(['admin', 'superadmin']);

        if (! $esDueno && ! $esAprobador && ! $esAdmin) {
            abort(403, 'No tienes autorización para descargar este PDF.');
        }

        $output = $pdf->render($solicitud);
        $filename = 'solicitud-'.$solicitud->tipo.'-'.$solicitud->id.'.pdf';

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => strlen($output),
        ]);
    }
}
