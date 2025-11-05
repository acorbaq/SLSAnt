<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Clase mínima para enviar EZPL a la cola de impresión.
 */
final class Printer
{
    /**
     * Escribe EZPL a un fichero temporal y lo envía por lpr a la cola indicada.
     * Devuelve true si el comando devuelve 0.
     */
    public static function printEzpl(string $ezpl, string $printerDevice = 'godex_raw', ?string $tmpfile = null): bool
    {
        // tmp proyecto
        $projectTmp = realpath(__DIR__ . '/../../tmp') ?: sys_get_temp_dir();
        if (!is_dir($projectTmp)) {
            @mkdir($projectTmp, 0755, true);
        }

        $tmpfile = $tmpfile ?? tempnam($projectTmp, 'etq_') . '.ezpl';
        $logPath = $projectTmp . '/print_debug.log';

        $w = @file_put_contents($tmpfile, $ezpl, LOCK_EX);
        if ($w === false) {
            $err = error_get_last();
            $entry = sprintf("[%s] ERROR writing tmpfile=%s err=%s\n", date('c'), $tmpfile, json_encode($err));
            @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
            return false;
        }

        $cmd = 'lpr -P ' . escapeshellarg($printerDevice) . ' ' . escapeshellarg($tmpfile) . ' 2>&1';
        exec($cmd, $output, $ret);

        $entry = sprintf("[%s] printer=%s tmp=%s exit=%d\nCMD: %s\nOUTPUT:\n%s\n\n",
            date('c'), $printerDevice, $tmpfile, $ret, $cmd, implode("\n", $output)
        );
        @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);

        return $ret === 0;
    }
}