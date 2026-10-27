<?php

namespace App\Platform\Settings;

enum SettingType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case String = 'string';
    /** A list of strings (e.g. locales, country codes). */
    case StringList = 'string_list';
    /** A path on the public disk (logo, favicon); exposed publicly as a URL. */
    case File = 'file';
}
