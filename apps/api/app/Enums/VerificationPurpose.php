<?php

namespace App\Enums;

enum VerificationPurpose: string
{
    /** Prove ownership of the contact the account was registered with. */
    case Verify = 'verify';

    case PasswordReset = 'password_reset';

    /** Prove ownership of a new contact before it replaces (or is added to) the account. */
    case ChangeContact = 'change_contact';
}
