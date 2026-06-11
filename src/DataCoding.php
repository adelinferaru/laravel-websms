<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms;

enum DataCoding: string
{
    case Gsm = 'GSM';
    case Ucs2 = 'UCS2';
}
