<?php

declare(strict_types=1);

namespace App\Services;

class TraductorEZPL
{
    // Obtener registro sanitario desde .env o usar valor por defecto
    private const DEFAULT_REGISTRO = 'RSX-0001-0001';


    
    public static function generateEZPL(array $data, int $copies = 1, int $daysValid = 2): string
    {
        $registro = trim((string)($_ENV['REGISTRO_SANITARIO'] ?? $_SERVER['REGISTRO_SANITARIO'] ?? getenv('REGISTRO_SANITARIO') ?: ''));
        if ($registro === '') {
            $registro = self::DEFAULT_REGISTRO;
        }
        $daysFromData = isset($data['days_valid']) ? intval($data['days_valid']) : null;
        $daysToUse = $daysFromData !== null ? $daysFromData : $daysValid;

        $producto     = strtoupper(trim((string)($data['nombreLb'] ?? $data['producto'] ?? '')));
        $ingredientes = trim((string)($data['ingredientesLb'] ?? $data['ingredientes'] ?? ''));
        $alergenos    = strtoupper(trim((string)($data['alergenosLb'] ?? $data['alergenos'] ?? '')));
        $lote         = trim((string)($data['loteCodigo'] ?? $data['lote'] ?? ''));
        $fechaCad     = $data['fechaCaducidad'] ?? self::computeExpiryDate($daysToUse);
        $conservacion = trim((string)($data['conservacionLb'] ?? $data['conservacion'] ?? 'CONSERVAR EN UN LUGAR FRESCO Y SECO'));
        $tipoElaboracion = intval($data['tipoElaboracion'] ?? 1); // 1: Elaboración, 2: Escandallo, 3: Envasado, 4: Congelado
        $fechaElaboracion = trim((string)($data['fechaElaboracion'] ?? ''));

        $ezpl = "";
        $ezpl .= "^XSETCUT,DOUBLECUT,0\n";
        $ezpl .= "^Q60,3\n";
        $ezpl .= "^W60\n";
        $ezpl .= "^H8\n";
        $ezpl .= "^P" . intval($copies) . "\n";
        $ezpl .= "^S4\n";
        $ezpl .= "^AD\n";
        $ezpl .= "^C1\n";
        $ezpl .= "^R16\n";
        $ezpl .= "~Q-16\n";
        $ezpl .= "^O0\n";
        $ezpl .= "^D0\n";
        $ezpl .= "^E18\n";
        $ezpl .= "~R255\n";
        $ezpl .= "^L\n";

        // Fecha tokens firmware
        $ezpl .= "Dy2-me-dd\n";
        $ezpl .= "Th:m:s\n";
        $ezpl .= "Y120,5,Logo.Resize10\n";
        $ezpl .= "Dy2-me-dd\n";
        $ezpl .= "Th:m:s\n";

        // Producto (nombre completo) usando reglas de centrado/lineas
        $ezpl .= self::renderTitleEZPL($producto);

        // Ingredientes envueltos
        $max_chars_per_line = 56;
        $ing_lines = $ingredientes === '' ? [] : self::wrapTextByChars($ingredientes, $max_chars_per_line);

        $headerY = 235;
        $predicted_alergenos_y = $headerY + 25 + 16 * count($ing_lines) + 5;
        if ($alergenos !== '' && $predicted_alergenos_y > 350) {
            $headerY = 215;
        }

        $ezpl .= "AA,162,{$headerY},1,1,0,0E,INGREDIENTES\n";

        $y = $headerY + 25;
        foreach ($ing_lines as $line) {
            $clean = trim($line);
            if ($clean === '') {
                $y += 16;
                continue;
            }
            $ezpl .= "AA,10,{$y},1,1,0,0E," . addslashes($clean) . "\n";
            $y += 16;
        }

        if ($alergenos !== '') {
            $y += 5;
            $ezpl .= "AA,10,{$y},1,1,0,0E,ALERGENOS: (" . addslashes($alergenos) . ")\n";
            $y += 22;
        }

        $cons_lines = explode("\n", wordwrap($conservacion, 48, "\n"));
        foreach ($cons_lines as $line) {
            $clean = trim($line);
            if ($clean === '') continue;
            $ezpl .= "AA,10,360,1,1,0,0E," . addslashes($clean) . "\n";
            $y += 16;
        }

        // Ajustes por tipo de elaboración
        switch ($tipoElaboracion) {
            case 1: // Elaboración
            case 2: // Escandallo
                $ezpl .= "AA,10,380,1,1,0,0E,Consumir preferentemente antes del\n";
                $ezpl .= "AB,90,400,1,1,0,0E," . addslashes($fechaCad) . "\n";
                break;
            case 3: // Envasado
                $ezpl .= "AA,10,385,1,1,0,0E,Fecha de envasado:\n";
                $ezpl .= "AA,160,385,1,1,0,0E," . addslashes($fechaElaboracion) . "\n";
                $ezpl .= "AA,10,405,1,1,0,0E,Fecha de caducidad:\n";
                $ezpl .= "AA,160,405,1,1,0,0E," . addslashes($fechaCad) . "\n";
                break;
            case 4: // Congelado
                $ezpl .= "AA,10,385,1,1,0,0E,Fecha de congelado:\n";
                $ezpl .= "AA,160,385,1,1,0,0E," . addslashes($fechaElaboracion) . "\n";
                $ezpl .= "AA,10,405,1,1,0,0E,Fecha de consumo preferente:\n";
                $ezpl .= "AA,160,405,1,1,0,0E," . addslashes($fechaCad) . "\n";
                break;
            default:
                // Por defecto, como elaboración
                $ezpl .= "AA,10,380,1,1,0,0E,Consumir preferentemente antes del\n";
                $ezpl .= "AB,90,400,1,1,0,0E," . addslashes($fechaCad) . "\n";
                break;
        }

        if ($lote !== '') {
            $ezpl .= "AA,350,380,1,1,0,0E,Lote\n";
            $charWidth = 12.0;
            $centerX = 361.0;
            $len = mb_strlen($lote, 'UTF-8');
            $totalWidth = $len * $charWidth;
            $half = $totalWidth / 2.0;
            $loteX = (int) round($centerX - $half);
            $minX = 40;
            $maxX = 350;
            $loteX = max($minX, min($maxX, $loteX));
            $ezpl .= "AB," . $loteX . ",400,1,1,0,0E," . addslashes($lote) . "\n";
        }

        $ezpl .= "AA,146,435,1,1,0,0E,Reg Nº " . addslashes($registro) . "\n";
        $ezpl .= "E\n";
 
        return $ezpl;
    }

    private static function computeExpiryDate(int $daysAhead = 2): string
    {
        return date('d/m/Y', strtotime("+{$daysAhead} days"));
    }

    private static function splitTitleLines(string $title, float $charWidth = 11.5, float $maxPoints = 220.0): array
    {
        $orig = $title;
        $titleTrimmed = trim($orig);
        if ($titleTrimmed === '') return [];
        $len = mb_strlen($orig);
        $width = $len * $charWidth;
        if ($width <= $maxPoints) return [trim($orig)];

        $half = (int) floor($len / 2);
        $leftPart = mb_substr($orig, 0, $half + 1);
        $leftSpace = mb_strrpos($leftPart, ' ');
        $rightSpace = mb_strpos($orig, ' ', $half);

        $splitPos = null;
        if ($leftSpace !== false && $rightSpace !== false) {
            $dLeft = $half - $leftSpace;
            $dRight = $rightSpace - $half;
            $splitPos = ($dLeft <= $dRight) ? $leftSpace : $rightSpace;
        } elseif ($leftSpace !== false) {
            $splitPos = $leftSpace;
        } elseif ($rightSpace !== false) {
            $splitPos = $rightSpace;
        } else {
            $splitPos = (int)ceil($len / 2);
        }

        $line1 = mb_substr($orig, 0, $splitPos);
        $line2 = mb_substr($orig, $splitPos + (($orig[$splitPos] ?? '') === ' ' ? 1 : 0));
        return [trim($line1), trim($line2)];
    }

    private static function computeCenteredX(string $text): int
    {
        $chars = mb_strlen($text);
        $half = $chars / 2.0;
        $offset = $half * 22.5;
        $x = 224 - $offset;
        return (int)round($x);
    }

    private static function renderTitleEZPL(string $title): string
    {
        $title = strtoupper(trim($title));
        if ($title === '') return '';
        $lines = self::splitTitleLines($title);
        $ezpl = "";
        if (count($lines) === 1) {
            $y = 175;
            $x = self::computeCenteredX($lines[0]);
            $ezpl .= sprintf("AD,%d,%d,1,1,0,0E,%s\n", $x, $y, addslashes($lines[0]));
        } else {
            $y1 = 150;
            $y2 = $y1 + 35;
            $x1 = self::computeCenteredX($lines[0]);
            $x2 = self::computeCenteredX($lines[1]);
            $ezpl .= sprintf("AD,%d,%d,1,1,0,0E,%s\n", $x1, $y1, addslashes($lines[0]));
            $ezpl .= sprintf("AD,%d,%d,1,1,0,0E,%s\n", $x2, $y2, addslashes($lines[1]));
        }
        return $ezpl;
    }

    private static function wrapTextByChars(string $text, int $maxChars = 60): array
    {
        $lines = [];
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = (strpos($text, "\n\n") !== false) ? explode("\n\n", $text) : [$text];

        foreach ($paragraphs as $para) {
            $para = str_replace("\n", ' ', $para);
            $para = trim(preg_replace('/\s+/u', ' ', $para));
            if ($para === '') {
                $lines[] = '';
                continue;
            }
            $words = preg_split('/\s+/u', $para);
            $current = '';
            foreach ($words as $w) {
                if (mb_strlen($w) > $maxChars) {
                    if ($current !== '') {
                        $lines[] = $current;
                        $current = '';
                    }
                    $pos = 0;
                    $len = mb_strlen($w);
                    while ($pos < $len) {
                        $chunk = mb_substr($w, $pos, $maxChars);
                        $lines[] = $chunk;
                        $pos += mb_strlen($chunk);
                    }
                    continue;
                }
                if ($current === '') {
                    $current = $w;
                } else {
                    if (mb_strlen($current) + 1 + mb_strlen($w) <= $maxChars) {
                        $current .= ' ' . $w;
                    } else {
                        $lines[] = $current;
                        $current = $w;
                    }
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
            $lines[] = '';
        }

        if (end($lines) === '') array_pop($lines);
        return $lines;
    }
}
