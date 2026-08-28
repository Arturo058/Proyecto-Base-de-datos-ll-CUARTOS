<?php
/** Funciones auxiliares de presentación (badges y cálculo de tiempo). */

function claseBadgeEstado(string $estado): string
{
    return match ($estado) {
        'Disponible'    => 'bg-success',
        'Ocupado'       => 'bg-danger',
        'Mantenimiento' => 'bg-warning text-dark',
        default         => 'bg-secondary',
    };
}

function claseBadgeReporte(string $estado): string
{
    return match ($estado) {
        'Pendiente'  => 'bg-warning text-dark',
        'En Proceso' => 'bg-info text-dark',
        'Resuelto'   => 'bg-success',
        'Cancelado'  => 'bg-secondary',
        default      => 'bg-secondary',
    };
}

function claseBadgePrioridad(string $prioridad): string
{
    return match ($prioridad) {
        'Alta'  => 'bg-danger',
        'Media' => 'bg-warning text-dark',
        'Baja'  => 'bg-secondary',
        default => 'bg-secondary',
    };
}

function claseBadgeLlamada(string $estado): string
{
    return match ($estado) {
        'Aplicada'    => 'bg-danger',
        'En Revision' => 'bg-warning text-dark',
        default       => 'bg-secondary',
    };
}

/**
 * Calcula el tiempo transcurrido desde la firma del contrato en formato
 * legible, p. ej. "Llevas 5 meses y 12 días".
 */
function tiempo_transcurrido(string $fecha_inicio): string
{
    try {
        $inicio = new DateTime($fecha_inicio);
        $ahora  = new DateTime('now');
        if ($inicio > $ahora) {
            return 'Tu contrato inicia próximamente';
        }
        $diff   = $inicio->diff($ahora);
        $partes = [];
        if ($diff->y > 0) $partes[] = $diff->y . ' año' . ($diff->y > 1 ? 's' : '');
        if ($diff->m > 0) $partes[] = $diff->m . ' mes' . ($diff->m > 1 ? 'es' : '');
        $partes[] = $diff->d . ' día' . ($diff->d !== 1 ? 's' : '');
        return 'Llevas ' . implode(' y ', $partes);
    } catch (Exception $e) {
        return '';
    }
}
