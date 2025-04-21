<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class DateController extends Controller
{
    public function toPersian($date)
    {
        date_default_timezone_set('Asia/Tehran');
        $formatter=new \IntlDateFormatter(
            'en-IR@calender=persian',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            'Asia/Tehran',
            \IntlDateFormatter::TRADITIONAL,
            "yyyy-MM-d HH:mm:ss"
        );
        $dateTime = \datetime::createfromformat('Y-m-d H:i:s',$date);
        return $formatter->format($dateTime);

    }
    public function toPersian2($date0)
    {
        $date = rtrim($date0, ".000");
        date_default_timezone_set('Asia/Tehran');

        $formatter = new \IntlDateFormatter(
            'en_IR@calendar=persian',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            'Asia/Tehran',
            \IntlDateFormatter::TRADITIONAL,
            "yyyy-MM-dd HH:mm:ss"
        );
        $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
        return $formatter->format($dateTime);


    }
    function jalali_to_gregorian($date)
    {
        $mod = '';
        $jy = explode('-',$date)[0];
        $jm = explode('-',$date)[1];
        $jd = explode('-',$date)[2];
        if ($jy > 979) {
            $gy = 1600;
            $jy -= 979;
        } else {
            $gy = 621;
        }

        $days = (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + 78 + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy += 400 * ((int)($days / 146097));
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * ((int)(--$days / 36524));
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy += 4 * ((int)(($days) / 1461));
        $days %= 1461;
        $gy += (int)(($days - 1) / 365);
        if ($days > 365) $days = ($days - 1) % 365;
        $gd = $days + 1;
        foreach (array(0, 31, ((($gy % 4 == 0) and ($gy % 100 != 0)) or ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31) as $gm => $v) {
            if ($gd <= $v) break;
            $gd -= $v;
        }

//        return ($mod === '') ? array($gy, $gm, $gd) : $gy . $mod . $gm . $mod . $gd;
        if ($gm<10){
            $gm = '0'.$gm;
        }
        if ($gd<10){
            $gd = '0'.$gd;
        }
        return $gy.'-'.$gm.'-'.$gd;
    }
    public function toGREGORIAN()
    {
        $jalaliDate = "1370-10-26 00:00:00";
        $formatterGregorian = new \IntlDateFormatter(
            'en_US', // English locale with Gregorian calendar
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            'UTC',
            \IntlDateFormatter::GREGORIAN,
            "yyyy-MM-d HH:mm:ss"
        );
        $dateTime = \datetime::createfromformat('Y-m-d H:i:s',$jalaliDate);
        return $formatterGregorian->format($dateTime);

    }
}
