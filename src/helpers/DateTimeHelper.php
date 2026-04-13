<?php
/**
 * Helper: DateTime utilities
 */

if (!function_exists('formatRouteDatetime')) {
    /**
     * Formatea fecha/hora de inicio y fin de ruta
     * 
     * @param string|null $start Fecha/hora de inicio en formato MySQL (YYYY-MM-DD HH:MM:SS)
     * @param string|null $end   Fecha/hora de fin en formato MySQL (YYYY-MM-DD HH:MM:SS)
     * @return string             Fecha formateada o string vacío
     */
    function formatRouteDatetime(?string $start, ?string $end = null): string {
        $isValid = fn($v) => $v && $v !== 'null' && $v !== 'undefined' && $v !== '';
        
        if (!$isValid($start) && !$isValid($end)) {
            return '';
        }
        
        $locale = function_exists('current_lang') ? current_lang() : 'es';
        $dateLocale = $locale === 'en' ? 'en-GB' : 'es-ES';
        
        $formatDate = function($dt) use ($dateLocale) {
            if (!$dt) return '';
            $d = DateTime::createFromFormat('Y-m-d H:i:s', $dt);
            if (!$d) {
                $d = DateTime::createFromFormat('Y-m-d H:i', $dt);
            }
            if (!$d) {
                $d = DateTime::createFromFormat('Y-m-d', $dt);
            }
            if ($d) {
                return $d->format('d M');
            }
            return '';
        };
        
        $formatTime = function($dt) use ($dateLocale) {
            if (!$dt) return '';
            $d = DateTime::createFromFormat('Y-m-d H:i:s', $dt);
            if (!$d) {
                $d = DateTime::createFromFormat('Y-m-d H:i', $dt);
            }
            if ($d) {
                return $d->format('H:i');
            }
            return '';
        };
        
        $startDate = $formatDate($start);
        $startTime = $formatTime($start);
        $endDate = $formatDate($end);
        $endTime = $formatTime($end);
        
        if ($startDate && $startTime && $endDate && $endTime) {
            return sprintf('%s %s - %s', $startDate, $startTime, $endTime);
        } else if ($startDate && $startTime) {
            return sprintf('%s %s', $startDate, $startTime);
        } else if ($startDate) {
            return $startDate;
        } else if ($startTime) {
            return $startTime;
        } else if ($endDate && $endTime) {
            return sprintf('%s %s', $endDate, $endTime);
        } else if ($endDate) {
            return $endDate;
        } else if ($endTime) {
            return $endTime;
        }
        
        return '';
    }
}
