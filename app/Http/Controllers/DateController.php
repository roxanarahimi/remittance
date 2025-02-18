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
