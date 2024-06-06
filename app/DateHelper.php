<?php

namespace App;
use DateTime;
use DateInterval;

class DateHelper {

    public static function nextUtilIfNotUtil(DateTime $date, $interval = 0): DateTime {
        $newDate = clone $date;

        if($newDate->format('N') <= 5) {
            return $newDate;
        }
 
        return DateHelper::nextUtil($newDate, $interval);
    }

    public static function nextUtil(DateTime $date, $interval = 0): DateTime {
        $newDate = clone $date;

        if($interval > 0) {
            $newDate->add(new DateInterval('P' . ($interval) . 'D'));
        }
        
        $weekDayDiff = ((int) $newDate->format('N')) > 5 ? ((int) $newDate->format('N') - 5) + 1 :  0;
        $newDate->add(new DateInterval('P' . ($weekDayDiff) . 'D'));

        return $newDate;
    }

    public static function validDate(DateTime $date): DateTime {
        return $date->format('Y-m-d') < (new DateTime('now'))->format('Y-m-d') ? new DateTime('now') : $date;
    }

    public static function calculateIntervalInDays(DateTime $dt1, DateTime $dt2) {
        $interval = $dt2->diff($dt1);
        return (int) $interval->d;
    }

    public static function dateInPast(DateTime $dt1, DateTime $dt2) {
        $interval = $dt2->diff($dt1);
        return $interval->invert == 1;
    }

    public static function calculateIntervalUtilInDays(DateTime $date1, DateTime $date2) {
        $interval = 0;
        $dt1 = clone $date1;
        $dt2 = clone $date2;

        if($dt2->format('Y-m-d') <= $dt1->format('Y-m-d')) {
            return 0;
        }

        while($dt1->format('Y-m-d') != $dt2->format('Y-m-d')) {
            $dt1->add(new DateInterval('P1D'));
            $n = $dt1->format('N');

            if($n <= 5) {
                $interval++;
            }
        }
        return (int) $interval;
    }

    public static function searchDiffInDateTimeArray(array $ar1, array $ar2) {
        foreach($ar1 as $key1 => $dt1) {
            if( ! isset($ar2[$key1]) ) {
                return true;
            }

            if( $ar1[$key1]->format('Y-m-d') != $ar2[$key1]->format('Y-m-d') ) {
                return true;
            }
        }

        return false;
    }

    public static function sumUtil(DateTime $date, $interval) {
        $newDate = clone $date;

        while($interval > 0) {
            $newDate->add(new DateInterval('P1D'));
            while($newDate->format('N') > 5) {
                $newDate->add(new DateInterval('P1D'));
            }
            $interval--;
        }

        return $newDate;
    }

    public static function subUtil(DateTime $date, $interval) {
        $newDate = clone $date;

        while($interval > 0) {
            $newDate->sub(new DateInterval('P1D'));
            while($newDate->format('N') > 5) {
                $newDate->sub(new DateInterval('P1D'));
            }
            $interval--;
        }

        return $newDate;
    }

    public static function sum(DateTime $date, $interval = 1) {
        $newDate = clone $date;
        $newDate->add(new DateInterval('P' . $interval . 'D'));
        return $newDate;
    }

    public static function sub(DateTime $date, $interval) {
        $newDate = clone $date;
        $newDate->sub(new DateInterval('P' . $interval . 'D'));
        return $newDate;
    }

    public static function compare(DateTime $dt1, DateTime $dt2) {
        return $dt1->format('Y-m-d') == $dt2->format('Y-m-d');
    }

}