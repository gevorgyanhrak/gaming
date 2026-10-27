<?php

return [
    'auth' => [
        'registration_closed' => 'Registration is currently closed.',
        'inactive' => 'This account is not active.',
        'invalid_login' => 'Enter a valid email address or an international phone number starting with +.',
        'phone_country' => 'Phone numbers from this country are not supported yet.',
        'username_reserved' => 'This username is reserved.',
        'username_format' => 'Usernames may contain only letters, digits and underscores.',
        'reset_sent' => 'If an account exists for that email or phone, we sent it a code.',
        'reset_done' => 'Your password has been reset. You can now log in.',
        'no_contact' => 'Your account has no :channel to verify.',
        'already_verified' => 'This :channel is already verified.',
        'code_sent' => 'We sent you a code.',
    ],
    'verification' => [
        'invalid' => 'The code is invalid or has expired.',
        'throttled' => 'Please wait :seconds seconds before requesting another code.',
    ],
    'contact' => [
        'same' => 'This is already your :channel.',
    ],
    'channels' => [
        'email' => 'email address',
        'phone' => 'phone number',
    ],
    'codes' => [
        'verify' => [
            'subject' => 'Your :app verification code',
            'intro' => 'Use this code to verify your email address.',
        ],
        'password_reset' => [
            'subject' => 'Reset your :app password',
            'intro' => 'Use this code to reset your password.',
        ],
        'change_contact' => [
            'subject' => 'Confirm your new :app email address',
            'intro' => 'Use this code to confirm your new email address.',
        ],
        'code_line' => 'Your code: :code',
        'expires' => 'The code expires in :minutes minutes.',
        'ignore' => 'If you did not request this, you can ignore this message.',
        'sms' => ':app code: :code',
    ],
    'settings' => [
        'default_locale_supported' => 'The default language must be one of the supported languages.',
    ],
    'maintenance' => [
        'default_message' => 'We are doing some maintenance. Please check back soon.',
    ],
    'moderation' => [
        'not_yourself' => 'You cannot moderate your own account.',
        'super_admin_only' => 'Only super admins can do this for staff accounts.',
        'keep_own_super_admin' => 'You cannot remove your own super admin role.',
        'use_unban' => 'This account is banned. Unban it instead.',
    ],
    'play' => [
        'unavailable' => 'This game mode is not available right now.',
        'too_many_games' => 'Finish one of your current games first.',
        'verification_required' => 'Verify your :channel to play.',
        'no_bot' => 'No bot is available at this difficulty.',
        'invite_invalid' => 'This invite code is not valid or the room is full.',
        'not_your_turn' => 'It is not your turn.',
        'illegal_move' => 'This move is not allowed.',
        'not_in_progress' => 'This game is not in progress.',
        'no_bot_offer' => 'There is no bot game to accept. You may have been matched with a player.',
        'out_of_date' => 'The game has moved on. Refresh and try again.',
    ],
];
