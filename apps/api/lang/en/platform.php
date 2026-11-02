<?php

return [
    'auth' => [
        'registration_closed' => 'Registration is currently closed.',
        'inactive' => 'This account is not active.',
        'banned' => 'Your account has been blocked. Contact support if you think this is a mistake.',
        'banned_until' => 'Your account is blocked until :date. Contact support if you think this is a mistake.',
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
        'needs_login' => 'The account needs an email address or a phone number to sign in with.',
        'not_yourself' => 'You cannot moderate your own account.',
        'super_admin_only' => 'Only super admins can do this for staff accounts.',
        'keep_own_super_admin' => 'You cannot remove your own super admin role.',
        'use_unban' => 'This account is banned. Unban it instead.',
    ],
    'reactions' => [
        'disabled' => 'Emojis and quick messages are switched off.',
        'closed' => 'This table no longer takes reactions.',
        'unknown' => 'This reaction is not available.',
        'no_such_player' => 'Choose another player at this table.',
    ],
    'play' => [
        'group_unavailable' => 'This group is not available for this game.',
        'bet_range' => 'Choose a bet from :min to :max Fishkas.',
        'group_balance' => 'You need at least :needed Fishkas to play in this group.',
        'unavailable' => 'This game mode is not available right now.',
        'too_many_games' => 'Finish one of your current games first.',
        'verification_required' => 'Verify your :channel to play.',
        'no_bot' => 'No bot is available at this difficulty.',
        'wrong_players' => 'This game mode can\'t be played with that many players.',
        'invite_invalid' => 'This invite code is not valid or the room is full.',
        'not_your_turn' => 'It is not your turn.',
        'illegal_move' => 'This move is not allowed.',
        'not_in_progress' => 'This game is not in progress.',
        'no_bot_offer' => 'There is no bot game to accept. You may have been matched with a player.',
        'out_of_date' => 'The game has moved on. Refresh and try again.',
    ],
    'fishka' => [
        'insufficient' => 'You need :needed Fishkas to play this game.',
        'bonus_not_needed' => 'You still have enough Fishkas to play.',
        'bonus_cooldown' => 'The free Fishka bonus is not available yet.',
        'adjust_invalid' => 'Enter an amount of zero or more and a reason.',
        'adjust_below_zero' => 'The balance cannot go below zero (it is :balance).',
    ],
];
