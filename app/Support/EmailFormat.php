<?php

namespace App\Support;

class EmailFormat
{
    public const PATTERN = '/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i';

    public const MESSAGE = 'Please enter a valid email address like test@domain.com.';
}
