<?php

namespace App;

use DateInterval;
use DateTime;

class BrazilHoliday
{
    /**
     * Feriados nacionais (inclui móveis comuns de folga).
     * Retorna array no formato: ['Y-m-d' => 'Nome do feriado'].
     */
    public static function holidaysForYear(int $year): array
    {
        $fixed = [
            sprintf('%04d-01-01', $year) => 'Confraternização Universal',
            sprintf('%04d-04-21', $year) => 'Tiradentes',
            sprintf('%04d-05-01', $year) => 'Dia do Trabalhador',
            sprintf('%04d-09-07', $year) => 'Independência do Brasil',
            sprintf('%04d-10-12', $year) => 'Nossa Senhora Aparecida',
            sprintf('%04d-11-02', $year) => 'Finados',
            sprintf('%04d-11-15', $year) => 'Proclamação da República',
            sprintf('%04d-12-25', $year) => 'Natal',
        ];

        $movable = self::movableHolidaysForYear($year);

        return $fixed + $movable;
    }

    public static function isHoliday($date): bool
    {
        $dt = $date instanceof DateTime ? (clone $date) : new DateTime(substr((string) $date, 0, 10));
        $year = (int) $dt->format('Y');
        $key = $dt->format('Y-m-d');

        $holidays = self::holidaysForYear($year);
        return array_key_exists($key, $holidays);
    }

    public static function holidayName($date): ?string
    {
        $dt = $date instanceof DateTime ? (clone $date) : new DateTime(substr((string) $date, 0, 10));
        $year = (int) $dt->format('Y');
        $key = $dt->format('Y-m-d');
        $holidays = self::holidaysForYear($year);

        return $holidays[$key] ?? null;
    }

    /**
     * Retorna feriados (nacionais) entre duas datas (inclusive).
     * Formato: [['date' => 'Y-m-d', 'name' => '...'], ...]
     */
    public static function holidaysBetween($startDate, $endDate): array
    {
        $start = $startDate instanceof DateTime ? (clone $startDate) : new DateTime(substr((string) $startDate, 0, 10));
        $end = $endDate instanceof DateTime ? (clone $endDate) : new DateTime(substr((string) $endDate, 0, 10));

        if ($start->format('Y-m-d') > $end->format('Y-m-d')) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }

        $years = range((int) $start->format('Y'), (int) $end->format('Y'));
        $byDate = [];
        foreach ($years as $year) {
            foreach (self::holidaysForYear((int) $year) as $date => $name) {
                if ($date >= $start->format('Y-m-d') && $date <= $end->format('Y-m-d')) {
                    $byDate[$date] = $name;
                }
            }
        }

        ksort($byDate);

        return array_map(function ($date) use ($byDate) {
            return ['date' => $date, 'name' => $byDate[$date]];
        }, array_keys($byDate));
    }

    private static function movableHolidaysForYear(int $year): array
    {
        // easter_date() existe no PHP 7+ e retorna timestamp (00:00 UTC) do domingo de Páscoa.
        $easterTs = easter_date($year);
        $easter = (new DateTime('@' . $easterTs))->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        $goodFriday = (clone $easter)->sub(new DateInterval('P2D'));
        $carnivalMonday = (clone $easter)->sub(new DateInterval('P48D'));
        $carnivalTuesday = (clone $easter)->sub(new DateInterval('P47D'));
        $corpusChristi = (clone $easter)->add(new DateInterval('P60D'));

        return [
            $carnivalMonday->format('Y-m-d') => 'Carnaval (segunda-feira)',
            $carnivalTuesday->format('Y-m-d') => 'Carnaval (terça-feira)',
            $goodFriday->format('Y-m-d') => 'Paixão de Cristo',
            $corpusChristi->format('Y-m-d') => 'Corpus Christi',
        ];
    }
}

