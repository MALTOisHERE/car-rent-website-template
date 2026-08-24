<?php

namespace App\Domain;

enum Transmission: int
{
    case Manual = 0;
    case Automatic = 1;
}
