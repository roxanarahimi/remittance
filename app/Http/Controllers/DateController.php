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
    public function toGREGORIAN($date)
    {
//        date_default_timezone_set('Asia/Tehran');
//        $formatter=new \IntlDateFormatter(
//            'en-US',
//            \IntlDateFormatter::FULL,
//            \IntlDateFormatter::FULL,
//            'UTC',
//            \IntlDateFormatter::GREGORIAN,
//            "yyyy-MM-d HH:mm:ss"
//        );
        $jalaliDate = "1402-11-29 00:00:00";

// Create a formatter for the Gregorian calendar
        $formatterGregorian = new \IntlDateFormatter(
            'en_US', // English locale with Gregorian calendar
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            'UTC',
            \IntlDateFormatter::GREGORIAN,
            "yyyy-MM-d HH:mm:ss"
        );

// Format the timestamp into a Gregorian date
        $gregorianDate = $formatterGregorian->format('Y-m-d H:i:s',$jalaliDate);

//        echo "Jalali Date: $jalaliDate\n";
        echo "Gregorian Date: ".$gregorianDate."\n";


//        $dateTime = \datetime::createfromformat('Y-m-d H:i:s',$date.' 00:00:00');
//        return $formatter->format($dateTime);

    }
}
