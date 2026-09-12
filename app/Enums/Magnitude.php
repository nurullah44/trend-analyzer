<?php

namespace App\Enums;

/** How large the opportunity behind a rising Subject could become. Never computed by the analyzer. */
enum Magnitude: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Big = 'big';
    case Generational = 'generational';
}
