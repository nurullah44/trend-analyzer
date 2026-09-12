<?php

namespace App\Enums;

/** The reviewer's ruling on an Alarm. */
enum Verdict: string
{
    case WorthConsidering = 'worth_considering';
    case Noise = 'noise';
}
