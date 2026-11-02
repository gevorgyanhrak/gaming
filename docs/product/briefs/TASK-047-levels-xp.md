# Owner brief — TASK-047 · Level & XP progression (2026-11-02)

Pasted by the owner on 2026-11-02 ("implement level logic"). Kept verbatim so any session can resume.

---

Implement a complete Level & XP Progression System across our gaming platform.

The goal is to make users feel rewarded for playing games, progressing, and reaching new levels.

1. User Level System

Every registered user must have a level.

Requirements:

* Every new user starts at Level 1.
* Users earn XP (experience points) by playing games and completing activities.
* When a user earns enough XP, they automatically advance to the next level.
* Display the user’s current level next to their avatar everywhere in the application:
    * User profile.
    * Game rooms.
    * Poker tables.
    * Player lists.
    * Leaderboards.
    * Chat and other relevant areas.

The level badge must be visually attractive and consistent throughout the application.

2. Level Progression & XP

Implement a progression system with a maximum of 30 levels initially.

Progression difficulty:

* Levels 1–30 should be relatively easy and fast to achieve.
* After Level 30, progression should become noticeably more challenging.
* Each subsequent level should require progressively more XP.
* Create a balanced XP curve that encourages users to keep playing without making progression feel impossible.

Important:

* Store XP and level in the database.
* Calculate level progression on the backend.
* Never trust XP or level values sent directly from the frontend.
* Prevent users from manipulating their XP.

Create a centralized, configurable XP calculation system so we can easily adjust progression requirements later.

3. Level Progress Bar

Users must always be able to understand their progress toward the next level.

Display:

* Current level.
* Current XP.
* XP required for the next level.
* A visual progress bar.
* Remaining XP needed to reach the next level.

Example:

LEVEL 12

████████░░ 80%

2,400 / 3,000 XP

600 XP until Level 13.

The progress bar should be visible in the user’s profile and other appropriate UI locations.

4. Level-Up Popup & Rewards

Whenever a user reaches a new level, display a beautiful level-up popup.

The popup should include:

* Animated level-up effect.
* Previous level → New level.
* Celebration animation.
* Confetti or other visual effects.
* Reward information.
* A button to claim or collect the reward, if applicable.

Each level advancement must provide a reward.

Possible rewards:

* Fishka bonuses.
* Special avatar frames.
* Exclusive emojis.
* Profile badges.
* Special visual effects.
* Other virtual items.

Create a configurable reward system so rewards can be assigned to individual levels.

Important:

* Rewards must be granted exactly once.
* Prevent duplicate reward claims.
* Store reward history in the database.
* If a user levels up while offline, show the pending level-up notification and reward when they return.

5. Level-Up Celebrations

Some level milestones should have special celebrations.

For example:

* Level 5: First milestone.
* Level 10: Special reward.
* Level 15: Premium badge.
* Level 20: Special avatar frame.
* Level 25: Exclusive reward.
* Level 30: Major celebration and special reward.

Make milestone rewards configurable.

6. Bot Level System

IMPORTANT: All bots must have relatively high levels.

Requirements:

* Bots should not appear as Level 1 users.
* Assign bots realistic, relatively high levels.
* Use different levels for different bots.
* Make their levels visible next to their avatars, just like real users.
* Ensure bot levels are consistent across different games and game rooms.

Bots must follow the same visual level presentation as real users.

However, bot levels must be managed separately from real-user XP progression. Bots should not artificially generate XP or receive real-user rewards.

7. Backend Architecture

Before implementation, inspect the existing database and backend architecture.

Implement:

* User level.
* Total XP.
* XP required for the next level.
* Level reward configuration.
* Reward transaction history.
* Level-up event handling.

Requirements:

* XP calculations must happen on the backend.
* Use atomic database transactions for XP updates and reward distribution.
* Prevent duplicate rewards.
* Prevent XP manipulation.
* Keep the system scalable and configurable.

8. UI/UX Design

The level system should feel like a professional gaming platform.

Design:

* Attractive level badges.
* Smooth progress bar animations.
* Beautiful level-up popup.
* Distinct milestone celebrations.
* Consistent visual presentation across all games.

Do not overload the interface.

The level badge should be compact but clearly visible next to the user’s avatar.

9. Testing

After implementation, test the complete flow:

1. New user starts at Level 1.
2. User earns XP after playing.
3. Progress bar updates correctly.
4. User reaches the next level.
5. Level-up popup appears.
6. Reward is granted exactly once.
7. Level is displayed correctly everywhere.
8. Multiple level-ups are handled correctly.
9. Offline level-ups are displayed after login.
10. Bots display high levels.
11. Bots do not receive real-user XP rewards.
12. Levels above 30 require progressively more XP.
13. Concurrent XP updates do not cause incorrect levels or duplicate rewards.

IMPORTANT FINAL REQUIREMENTS

* First analyze the entire existing project.
* Reuse existing architecture and components.
* Do not break the existing fishka economy or gaming logic.
* Integrate XP rewards carefully with the existing balance system.
* Do not introduce unnecessary dependencies.
* Make sure the feature works end-to-end, not just visually.
* Test all affected games and user flows.
* Fix any issues discovered during testing.

After implementation, provide a summary of the XP formula, level progression rules, reward configuration, database changes, and files modified.
