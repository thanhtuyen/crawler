<?php
/**
 * Created by ThinhNH
 * Date: 9/24/15 4:10 PM
 */

function removeSpace($str) {
    $str = preg_replace("/[\r\n]+/", "\n", $str);
    $result = preg_replace('/ +/', ' ',$str);
    return $result;

}

$str = "Nakonsithammarat
                                                mueang
                                            Nakorn Si Thamarat
                                            80000
                                            Thailand";

$str = "

 2010 :


 Country :
 Thailand ( Bangkok )

 Rajamangala University of Technology Phra Nakhon
 Education Level :
 Bachelor's Degree
 Certificate : Bachelor of TechnologyMajor : television and radio broadcastingGPA : 3.16

 2006 :


 Country :
 Thailand ( Bangkok )

 Mathayom Wat Dusitaram School
 Education Level :
 Senior High School
 Certificate : Mathayom SixMajor : Math-EnglishGPA : 3.21";
echo $str;

echo removeSpace($str);
echo "\n";