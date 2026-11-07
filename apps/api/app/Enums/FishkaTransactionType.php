<?php

namespace App\Enums;

/**
 * Why a wallet changed. Positive amounts are credits, negative ones debits.
 */
enum FishkaTransactionType: string
{
    case RegistrationBonus = 'registration_bonus';
    case DailyLoginBonus = 'daily_login_bonus';
    case GameEntryFee = 'game_entry_fee';
    /** A fee, stake or buy-in given back because the game never started (a waiting room was left or closed). */
    case GameEntryRefund = 'game_entry_refund';
    /** The bet each player stakes at a table of a stake game. */
    case GameStake = 'game_stake';
    /** A share of a stake game's pot (or the stake back after a draw). */
    case GamePayout = 'game_payout';
    /** Fishkas brought to a poker table (the buy-in). */
    case PokerBet = 'poker_bet';
    /** Fishkas taken away from a poker table: what was left of the buy-in plus winnings. */
    case PokerWinning = 'poker_winning';
    case AdminCredit = 'admin_credit';
    case AdminDebit = 'admin_debit';
    case InsufficientBalanceBonus = 'insufficient_balance_bonus';
    /** A level's reward, collected from the level-up popup (ADR 0018). */
    case LevelReward = 'level_reward';
}
