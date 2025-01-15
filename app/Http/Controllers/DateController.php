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
            "yyyy-MM-d H:mm:ss"
        );


        $dateTime = \datetime::createfromformat('Y-m-d H:i:s',$date);
        return $formatter->format($dateTime);

    }
}
