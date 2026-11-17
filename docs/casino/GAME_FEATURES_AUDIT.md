# Game features audit — engine slots

Generated 2026-11-17 from the engine itself (`slot-engine list`, `engine/measured.json`); regenerate after changing the
engine. Owner decision (ADR 0026): games keep their makers' names and are grouped by maker.

**347 slot games** on 169 distinct reel-set lists, by 10 makers; **322 verified** (switched on), **25 off** (their play does not pay their label; see the end). Keno (6 games) is excluded.

How a game is verified: `slot-engine measure` plays it through the same spin path the platform uses (free games and
cascade falls carried in the saved state) — 200,000 paid spins, 2,000,000 for any game first found more than 3 points
off — at the reel set closest to 95 %, and compares the RTP paid with the set's label. Within 3 points: verified.

| Maker | Games | Verified |
|---|---:|---:|
| CT Interactive | 149 | 135 |
| Novomatic | 86 | 79 |
| AGT | 64 | 64 |
| NetEnt | 22 | 22 |
| Playtech | 8 | 6 |
| IGT | 5 | 5 |
| Megajack | 4 | 4 |
| Aristocrat | 3 | 3 |
| BetSoft | 3 | 2 |
| Play'n GO | 3 | 2 |

Feature counts (from each algorithm's flags):

| Feature | Games |
|---|---:|
| pays left | 319 |
| scatters | 301 |
| line select | 267 |
| wilds | 249 |
| free games retrigger | 167 |
| free games multipliers | 102 |
| wild multipliers | 89 |
| free games own reels | 87 |
| reel wilds | 39 |
| wild scatters | 15 |
| cascade | 15 |
| free games | 12 |
| full screen multiplier | 11 |
| jackpot | 10 |
| pays both ways | 10 |
| expanding wilds | 9 |
| cluster pays | 7 |
| pays anywhere | 6 |
| stacked scatters | 4 |
| multipliers | 4 |
| free games retrigger once | 4 |
| cascade features | 4 |
| symbols turn wild | 4 |
| pick mode | 4 |
| big symbols | 3 |
| mixed pays | 3 |
| bonus mode | 2 |
| big wilds | 1 |

Columns: **Grid** reels × rows; **Lines/ways** the game's lines (selectable when `line select`) or ways;
**Price** what a spin costs at bet 1 with the default lines; **RTP sets** the reel sets the game has (low–high, count);
**Measured** RTP paid at the set closest to 95 % (label in brackets).

## CT Interactive

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| 100 Burning Brilliants | `ctinteractive/100burningbrilliants` | 5×4 | 100 lines | 100 | 88.9–111.2 (24) | pays left, cascade, scatters, wilds | 93.27 (95.02) | on |
| 20 Clovers Hot | `ctinteractive/20clovershot` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 93.84 (95.04) | on |
| 20 Dice Party | `ctinteractive/20diceparty` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 97.29 (95.04) | on |
| 20 Fruitata Wins | `ctinteractive/20fruitatawins` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 95.70 (95.04) | on |
| 20 Mega Fresh | `ctinteractive/20megafresh` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 94.93 (95.04) | on |
| 20 Mega Slot | `ctinteractive/20megaslot` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 96.67 (95.04) | on |
| 20 Mega Star | `ctinteractive/20megastar` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 94.50 (95.04) | on |
| 20 Roosters | `ctinteractive/20roosters` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 93.41 (95.04) | on |
| 20 Shining Coins | `ctinteractive/20shiningcoins` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 96.52 (95.04) | on |
| 20 Star Party | `ctinteractive/20starparty` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 95.98 (95.04) | on |
| 30 Fruitata Wins | `ctinteractive/30fruitatawins` | 5×3 | 30 lines | 30 | 84.0–111.3 (31) | pays left, cascade, scatters, wilds | 95.20 (95.02) | on |
| 30 Treasures | `ctinteractive/30treasures` | 5×3 | 30 lines | 30 | 86.8–108.4 (25) | pays left, line select, scatters, wilds | 95.87 (95.07) | on |
| 40 Brilliants | `ctinteractive/40brilliants` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 92.59 (95.13) | on |
| 40 Diamond Treasures | `ctinteractive/40diamondtreasures` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 96.56 (95.13) | on |
| 40 Dice Treasures | `ctinteractive/40dicetreasures` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 96.09 (95.13) | on |
| 40 Fruitata Wins | `ctinteractive/40fruitatawins` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 94.33 (95.13) | on |
| 40 Hell's Cherries | `ctinteractive/40hellscherries` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 97.20 (95.13) | on |
| 40 Mega Slot | `ctinteractive/40megaslot` | 5×4 | 40 lines | 40 | 90.0–114.7 (19) | pays left, line select, scatters, wilds | 94.33 (95.08) | on |
| 40 Roosters | `ctinteractive/40roosters` | 5×4 | 40 lines | 40 | 90.0–114.7 (19) | pays left, line select, scatters, wilds | 95.03 (95.08) | on |
| 40 Shining Coins | `ctinteractive/40shiningcoins` | 5×4 | 40 lines | 40 | 90.0–114.7 (19) | pays left, line select, scatters, wilds | 93.97 (95.08) | on |
| 40 Shining jewels | `ctinteractive/40shiningjewels` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 93.36 (95.13) | on |
| 50 Shining Jewels | `ctinteractive/50shiningjewels` | 5×4 | 50 lines | 50 | 84.9–114.5 (29) | pays left, line select, scatters, wilds | 95.47 (95.05) | on |
| 50 Treasures | `ctinteractive/50treasures` | 5×4 | 50 lines | 50 | 84.9–114.5 (29) | pays left, line select, scatters, wilds | 96.16 (95.05) | on |
| Alaska Wild | `ctinteractive/alaskawild` | 5×4 | 50 lines | 50 | 86.3–109.8 (24) | pays left, line select, free games retrigger, scatters, wilds | 94.16 (95.10) | on |
| Amazons Spear | `ctinteractive/amazonsspear` | 5×3 | 25 lines | 25 | 88.8–109.5 (32) | pays left, line select, free games retrigger, free games own reels, scatters, reel wilds | 93.64 (95.01) | on |
| American Gigolo | `ctinteractive/americangigolo` | 5×3 | 30 lines | 30 | 82.6–109.3 (26) | pays left, line select, free games retrigger, scatters, wilds | 94.58 (95.06) | on |
| Aztec Empress | `ctinteractive/aztecempress` | 5×3 | 15 lines | 15 | 85.9–115.9 (20) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 93.98 (95.17) | on |
| Banana Party | `ctinteractive/bananaparty` | 5×3 | 25 lines | 25 | 87.2–114.0 (19) | pays left, line select, scatters, wilds | 96.12 (95.05) | on |
| Bavarian Forest | `ctinteractive/bavarianforest` | 5×3 | 25 lines | 25 | 87.7–111.3 (22) | pays left, line select, free games retrigger once, free games own reels, scatters, wilds | 96.16 (95.03) | on |
| Beetle Star | `ctinteractive/beetlestar` | 5×3 | 10 lines | 10 | 83.3–110.0 (25) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 95.68 (95.00) | on |
| Big Joker | `ctinteractive/bigjoker` | 5×3 | 20 lines | 20 | 83.5–110.3 (44) | pays left, scatters, reel wilds | 96.62 (95.05) | on |
| Black Pharaoh | `ctinteractive/blackpharaoh` | 5×3 | 20 lines | 20 | 87.9–108.7 (27) | pays left, line select, free games retrigger, scatters, wilds | 95.11 (95.04) | on |
| Bloody Princess | `ctinteractive/bloodyprincess` | 5×3 | 20 lines | 20 | 96.0–96.0 (1) | pays left, line select, jackpot, free games retrigger, free games multipliers, scatters, wilds | 96.44 (95.96) | on |
| Britania | `ctinteractive/britania` | 5×3 | 20 lines | 20 | 82.3–115.7 (21) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 95.38 (95.19) | on |
| Burning Flower | `ctinteractive/burningflower` | 5×3 | 5 lines | 5 | 86.1–111.5 (26) | pays left, line select, scatters, reel wilds | 96.63 (95.06) | on |
| Caribbean Adventure | `ctinteractive/caribbeanadventure` | 5×3 | 10 lines | 10 | 85.3–111.3 (23) | pays left, cluster pays, free games retrigger, scatters, wilds | 94.76 (94.98) | on |
| Cave of Clovers | `ctinteractive/caveofclovers` | 5×4 | 100 lines | 100 | 90.0–114.7 (19) | pays left, scatters, wilds | 93.86 (95.08) | on |
| Celestial Ruler | `ctinteractive/celestialruler` | 5×3 | 5 lines | 5 | 83.5–110.3 (44) | pays left, scatters, reel wilds | 93.55 (95.05) | on |
| Champagne and Fruits | `ctinteractive/champagneandfruits` | 5×3 | 20 lines | 20 | 96.0–96.0 (1) | pays left, line select, jackpot, free games retrigger, free games multipliers, scatters, wilds | 95.83 (95.96) | on |
| Cherry Crown | `ctinteractive/cherrycrown` | 5×3 | 20 lines | 20 | 90.9–109.0 (20) | pays left, scatters, expanding wilds | 97.36 (95.21) | on |
| Chilli Bomb | `ctinteractive/chillibomb` | 5×3 | 20 lines | 20 | 92.7–111.8 (5) | pays left, scatters, expanding wilds | 88.85 (95.82) | **off** |
| Christmas Storm | `ctinteractive/christmasstorm` | 5×3 | 30 lines | 30 | 84.0–111.3 (31) | pays left, cascade, scatters, wilds | 95.06 (95.02) | on |
| Clover Gems | `ctinteractive/clovergems` | 5×3 | 25 lines | 25 | 87.2–114.0 (19) | pays left, line select, scatters, wilds | 98.00 (95.05) | on |
| Clover Joker | `ctinteractive/cloverjoker` | 5×3 | 5 lines | 5 | 86.8–111.5 (18) | pays left, line select, scatters, reel wilds | 97.26 (95.05) | on |
| Clover Party | `ctinteractive/cloverparty` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 94.02 (95.04) | on |
| Clover Wheel | `ctinteractive/cloverwheel` | 5×3 | 20 lines | 20 | 87.8–110.4 (12) | pays left, scatters, wilds | 45935.41 (95.76) | **off** |
| Colibri Wild | `ctinteractive/colibriwild` | 5×4 | 40 lines | 40 | 88.0–115.6 (25) | pays left, scatters, expanding wilds | 101.52 (95.26) | **off** |
| Columbus Treasure | `ctinteractive/columbustreasure` | 5×3 | 10 lines | 10 | 86.5–112.9 (23) | pays left, line select, free games retrigger, scatters, wilds, wild multipliers | 95.81 (95.09) | on |
| Crown and Orb | `ctinteractive/crownandorb` | 5×3 | 10 lines | 10 | 91.8–112.1 (19) | pays left, line select, scatters | 94.65 (94.88) | on |
| Dancing Bananas | `ctinteractive/dancingbananas` | 5×3 | 10 lines | 10 | 86.8–111.5 (18) | pays left, line select, scatters, reel wilds | 95.44 (95.05) | on |
| Dancing Dragons | `ctinteractive/dancingdragons` | 5×3 | 10 lines | 10 | 86.8–111.5 (18) | pays left, line select, scatters, reel wilds | 93.75 (95.05) | on |
| Dark Woods | `ctinteractive/darkwoods` | 5×3 | 30 lines | 30 | 84.0–111.3 (31) | pays left, cascade, scatters, wilds | 94.49 (95.02) | on |
| Devil's Fruits | `ctinteractive/devilsfruits` | 3×3 | 5 lines | 5 | 88.9–113.7 (22) | mixed pays, wilds, wild multipliers | 95.33 (95.00) | on |
| Disco Babylon | `ctinteractive/discobabylon` | 5×3 | 10 lines | 10 | 83.3–110.8 (21) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 93.88 (94.95) | on |
| Double Hot Habanero | `ctinteractive/doublehothabanero` | 5×3 | 5 lines | 5 | 85.9–116.2 (24) | pays left, scatters, wilds, wild multipliers | 93.03 (95.17) | on |
| Easter Bonanza | `ctinteractive/easterbonanza` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 96.64 (95.13) | on |
| Egg and Rooster | `ctinteractive/eggandrooster` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 94.40 (95.04) | on |
| English Rose | `ctinteractive/englishrose` | 5×3 | 15 lines | 15 | 87.4–114.4 (27) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 96.52 (94.91) | on |
| FC Magic | `ctinteractive/fcmagic` | 5×3 | 5 lines | 5 | 86.1–111.5 (26) | pays left, line select, scatters, reel wilds | 96.27 (95.06) | on |
| Fire Dozen | `ctinteractive/firedozen` | 5×4 | 40 lines | 40 | 88.0–115.6 (25) | pays left, scatters, expanding wilds | 101.42 (95.26) | **off** |
| Fire King | `ctinteractive/fireking` | 5×3 | 5 lines | 5 | 85.8–110.9 (18) | pays left, line select, full screen multiplier, scatters, wilds | 97.41 (94.94) | on |
| Forest Nymph | `ctinteractive/forestnymph` | 5×3 | 15 lines | 15 | 87.4–114.4 (27) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 94.62 (94.91) | on |
| Fortune Fish | `ctinteractive/fortunefish` | 5×3 | 25 lines | 25 | 87.2–114.0 (19) | pays left, line select, scatters, wilds | 93.09 (95.05) | on |
| Fortune Pyramid | `ctinteractive/fortunepyramid` | 5×3 | 20 lines | 20 | 87.2–114.0 (19) | pays left, line select, scatters, wilds | 95.57 (95.05) | on |
| Fruit Feast | `ctinteractive/fruitfeast` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 95.33 (95.13) | on |
| Fruit Galaxy | `ctinteractive/fruitgalaxy` | 5×4 | 40 lines | 40 | 89.5–114.3 (14) | pays left, line select, scatters, reel wilds | 93.50 (94.97) | on |
| Fruits of Desire | `ctinteractive/fruitsofdesire` | 5×3 | 5 lines | 5 | 86.1–111.5 (26) | pays left, line select, scatters, reel wilds | 95.97 (95.06) | on |
| Full of Luck | `ctinteractive/fullofluck` | 5×3 | 15 lines | 15 | 85.9–115.9 (20) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 94.59 (95.17) | on |
| Full of Treasures | `ctinteractive/fulloftreasures` | 5×3 | 20 lines | 20 | 88.8–111.0 (21) | pays left, line select, scatters, wilds, wild multipliers | 87.96 (95.05) | **off** |
| Fusion Fruit Beat | `ctinteractive/fusionfruitbeat` | 5×3 | 20 lines | 20 | 90.5–113.2 (19) | pays left, line select, scatters, wilds | 95.10 (95.03) | on |
| Goblin's Gold | `ctinteractive/goblinsgold` | 5×3 | 20 lines | 20 | 87.3–109.6 (17) | pays left, line select, free games retrigger, free games multipliers, scatters, reel wilds | 93.92 (95.09) | on |
| Goddess of Bells | `ctinteractive/goddessofbells` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.73 (95.13) | on |
| Golden Acorn | `ctinteractive/goldenacorn` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 95.88 (95.13) | on |
| Golden Amulet | `ctinteractive/goldenamulet` | 5×3 | 5 lines | 5 | 92.5–112.5 (12) | scatters | 94.75 (95.24) | on |
| Golden Flower Of Life | `ctinteractive/goldenfloweroflife` | 5×3 | 20 lines | 20 | 85.9–106.1 (18) | pays left, line select, scatters, wilds | 92.33 (95.14) | on |
| Groovy Automat | `ctinteractive/groovyautomat` | 5×3 | 5 lines | 5 | 92.5–112.5 (12) | scatters | 95.46 (95.24) | on |
| Groovy Powers | `ctinteractive/groovypowers` | 5×3 | 20 lines | 20 | 87.4–118.7 (20) | pays left, line select, bonus mode, scatters, wilds, reel wilds | 94.96 (95.00) | on |
| Guardian of Asgard | `ctinteractive/guardianofasgard` | 5×3 | clusters/other | 40 | 83.5–114.3 (26) | cluster pays, cascade, scatters, wilds | 96.43 (95.03) | on |
| Halloween Fruits | `ctinteractive/halloweenfruits` | 5×3 | 30 lines | 30 | 83.2–110.7 (18) | pays left, line select, free games retrigger, free games own reels, scatters, wilds | 93.98 (95.07) | on |
| Halloween Hot | `ctinteractive/halloweenhot` | 5×3 | 5 lines | 5 | 85.8–110.9 (18) | pays left, line select, full screen multiplier, scatters, wilds | 95.41 (94.94) | on |
| Hell's Cherries | `ctinteractive/hellscherries` | 3×3 | 5 lines | 5 | 86.3–109.2 (18) | wilds | 95.23 (94.22) | on |
| Hell's Hot 7's | `ctinteractive/hellshot7s` | 5×3 | 5 lines | 5 | 83.5–115.6 (41) | pays left, line select, scatters, wilds, wild multipliers | 95.37 (95.07) | on |
| Hell's Sevens | `ctinteractive/hellssevens` | 3×3 | 5 lines | 5 | 86.3–109.2 (18) | wilds | 93.93 (94.22) | on |
| Hit the Hot | `ctinteractive/hitthehot` | 5×3 | 20 lines | 20 | 87.9–111.4 (16) | pays left, line select, scatters, wilds | 94.23 (95.00) | on |
| Hot 7's x2 | `ctinteractive/hot7sx2` | 5×3 | 5 lines | 5 | 83.5–115.6 (41) | pays left, line select, scatters, wilds, wild multipliers | 95.50 (95.07) | on |
| Hyper Cuber | `ctinteractive/hypercuber` | 5×3 | clusters/other | 3 | 80.0–125.6 (30) | cluster pays, cascade, free games retrigger, free games own reels, free games multipliers, scatters, wilds | 82.32 (95.18) | **off** |
| Ice Rubies | `ctinteractive/icerubies` | 5×4 | 40 lines | 40 | 88.0–115.6 (25) | pays left, scatters, expanding wilds | 101.32 (95.26) | **off** |
| Island Vacation | `ctinteractive/islandvacation` | 5×3 | 15 lines | 15 | 82.9–115.6 (27) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.18 (95.05) | on |
| Jade Heaven | `ctinteractive/jadeheaven` | 5×3 | 15 lines | 15 | 87.5–114.5 (27) | pays left, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 94.94 (95.00) | on |
| Jaguar Warrior | `ctinteractive/jaguarwarrior` | 5×3 | 20 lines | 20 | 86.8–111.5 (18) | pays left, line select, scatters, reel wilds | 93.88 (95.05) | on |
| Jolly Beluga Whales | `ctinteractive/jollybelugawhales` | 5×3 | 20 lines | 20 | 86.1–110.0 (23) | pays left, free games retrigger, reel wilds | 97.28 (95.23) | on |
| King of Clovers | `ctinteractive/kingofclovers` | 5×3 | 30 lines | 30 | 86.2–106.6 (25) | pays left, full screen multiplier, cascade, free games retrigger, free games own reels, scatters, wilds | 91.93 (95.16) | **off** |
| Lady Emerald | `ctinteractive/ladyemerald` | 5×3 | 20 lines | 20 | 90.5–113.2 (19) | pays left, line select, scatters, wilds | 96.54 (95.03) | on |
| Lord of Fortune | `ctinteractive/lordoffortune` | 5×3 | 5 lines | 5 | 86.8–111.5 (18) | pays left, line select, scatters, reel wilds | 96.90 (95.05) | on |
| Lord of Luck | `ctinteractive/lordofluck` | 5×4 | 40 lines | 40 | 89.5–114.3 (14) | pays left, line select, scatters, reel wilds | 94.23 (94.97) | on |
| Lucky 3 Penguins | `ctinteractive/lucky3penguins` | 5×3 | 20 lines | 20 | 86.4–112.0 (26) | pays left, free games retrigger, reel wilds | 95.88 (95.02) | on |
| Lucky Clover | `ctinteractive/luckyclover` | 5×3 | 5 lines | 5 | 86.1–111.5 (26) | pays left, line select, scatters, reel wilds | 95.19 (95.06) | on |
| Lucky Clover 10 | `ctinteractive/luckyclover10` | 5×3 | 10 lines | 10 | 86.8–111.5 (18) | pays left, line select, scatters, reel wilds | 94.28 (95.05) | on |
| Lucky Clover 20 | `ctinteractive/luckyclover20` | 5×3 | 20 lines | 20 | 86.8–111.5 (18) | pays left, line select, scatters, reel wilds | 96.81 (95.05) | on |
| Lucky Clover x3 | `ctinteractive/luckycloverx3` | 5×3 | 5 lines | 5 | 90.0–107.0 (22) | pays left, line select, full screen multiplier, scatters, reel wilds | 95.27 (95.02) | on |
| Lucky Dollar | `ctinteractive/luckydollar` | 5×3 | 30 lines | 30 | 82.3–110.9 (21) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 96.23 (94.94) | on |
| Magic Jewels | `ctinteractive/magicjewels` | 5×3 | 25 lines | 25 | 85.3–110.1 (20) | pays left, cascade, cascade features, free games retrigger, free games multipliers, wilds | 74.28 (94.56) | **off** |
| Magician Dreaming | `ctinteractive/magiciandreaming` | 5×3 | 10 lines | 10 | 87.5–114.5 (27) | pays left, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 94.80 (95.00) | on |
| Mighty Kraken | `ctinteractive/mightykraken` | 5×3 | 5 lines | 5 | 86.1–111.5 (26) | pays left, line select, scatters, reel wilds | 95.03 (95.06) | on |
| Mighty Moon | `ctinteractive/mightymoon` | 5×3 | 20 lines | 20 | 87.8–110.4 (12) | pays left, scatters, wilds | 45591.53 (95.76) | **off** |
| Mighty Rex | `ctinteractive/mightyrex` | 5×3 | 25 lines | 25 | 87.7–111.3 (22) | pays left, line select, free games retrigger once, free games own reels, scatters, wilds | 96.12 (95.03) | on |
| Misty Forest | `ctinteractive/mistyforest` | 5×3 | 20 lines | 20 | 87.3–109.6 (17) | pays left, line select, free games retrigger, free games multipliers, scatters, reel wilds | 95.08 (95.09) | on |
| Money Pipe | `ctinteractive/moneypipe` | 5×4 | 40 lines | 40 | 87.5–108.0 (17) | pays left, free games retrigger, scatters, expanding wilds | 100.79 (95.00) | **off** |
| Monkey Kingdom | `ctinteractive/monkeykingdom` | 5×3 | 20 lines | 20 | 96.0–96.0 (1) | pays left, line select, jackpot, free games retrigger, free games multipliers, scatters, wilds | 96.61 (95.96) | on |
| More Dragons | `ctinteractive/moredragons` | 5×4 | 40 lines | 40 | 87.5–108.0 (17) | pays left, free games retrigger, scatters, expanding wilds | 101.37 (95.00) | **off** |
| Mountain Heroes | `ctinteractive/mountainheroes` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 95.59 (95.13) | on |
| Mountain Song Quechua | `ctinteractive/mountainsongquechua` | 5×3 | 20 lines | 20 | 87.7–111.3 (22) | pays left, line select, free games retrigger once, free games own reels, scatters, wilds | 96.84 (95.03) | on |
| Navy Girl | `ctinteractive/navygirl` | 5×3 | 10 lines | 10 | 87.5–114.5 (27) | pays left, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 93.34 (95.00) | on |
| Neon Bananas | `ctinteractive/neonbananas` | 5×3 | 20 lines | 20 | 87.8–110.4 (12) | pays left, scatters, wilds | 45790.97 (95.76) | **off** |
| Nordic Song | `ctinteractive/nordicsong` | 5×3 | 25 lines | 25 | 84.3–125.8 (22) | pays left, line select, free games retrigger, free games own reels, scatters, wilds | 93.09 (95.11) | on |
| Ocean Legends | `ctinteractive/oceanlegends` | 5×3 | 25 lines | 25 | 79.0–111.2 (20) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 92.31 (95.07) | on |
| Penguin Party | `ctinteractive/penguinparty` | 5×3 | 20 lines | 20 | 86.4–112.0 (26) | pays left, free games retrigger, reel wilds | 93.96 (95.02) | on |
| Purple Fruits | `ctinteractive/purplefruits` | 5×3 | 5 lines | 5 | 91.8–112.1 (19) | pays left, line select, scatters | 94.05 (94.88) | on |
| Purple Hot 2 | `ctinteractive/purplehot2` | 5×3 | 25 lines | 25 | 91.7–112.1 (19) | pays left, line select, scatters | 95.36 (95.04) | on |
| Pyramid of Gold | `ctinteractive/pyramidofgold` | 5×4 | 50 lines | 50 | 86.3–109.8 (24) | pays left, line select, free games retrigger, scatters, wilds | 95.38 (95.10) | on |
| Queen of Flames | `ctinteractive/queenofflames` | 5×4 | 40 lines | 40 | 89.5–114.3 (14) | pays left, line select, scatters, reel wilds | 96.30 (94.97) | on |
| Rainbow Charm | `ctinteractive/rainbowcharm` | 5×3 | clusters/other | 12 | 82.0–109.5 (48) | cluster pays, multipliers, scatters | 95.97 (95.04) | on |
| Rodeo Power | `ctinteractive/rodeopower` | 5×3 | 243 ways | 25 | 89.3–114.6 (17) | pays left, free games retrigger, scatters, wilds, wild multipliers | 95.06 (95.05) | on |
| Sapphire Lagoon | `ctinteractive/sapphirelagoon` | 5×3 | 25 lines | 25 | 84.4–112.2 (21) | pays left, line select, free games retrigger, free games multipliers, stacked scatters, wilds | 95.77 (95.35) | on |
| Satyr and Nymph | `ctinteractive/satyrandnymph` | 5×3 | 20 lines | 20 | 90.9–109.0 (20) | pays left, scatters, expanding wilds | 95.03 (95.21) | on |
| Satyr and Nymph Dice | `ctinteractive/satyrandnymphdice` | 5×3 | 20 lines | 20 | 90.9–109.0 (20) | pays left, scatters, expanding wilds | 96.54 (95.21) | on |
| Shining Treasures | `ctinteractive/shiningtreasures` | 5×3 | 15 lines | 15 | 84.0–110.8 (22) | pays left, line select, scatters, wilds | 92.98 (95.12) | on |
| Smiley x Wild | `ctinteractive/smileyxwild` | 5×3 | 20 lines | 20 | 88.8–111.0 (21) | pays left, line select, scatters, wilds, wild multipliers | 88.44 (95.05) | **off** |
| Space Fruits | `ctinteractive/spacefruits` | 5×3 | 20 lines | 20 | 87.4–118.7 (20) | pays left, line select, bonus mode, scatters, wilds, reel wilds | 97.55 (95.00) | on |
| Sugar Town | `ctinteractive/sugartown` | 5×3 | clusters/other | 40 | 83.5–114.3 (26) | cluster pays, cascade, scatters, wilds | 95.78 (95.03) | on |
| The Great Cabaret | `ctinteractive/thegreatcabaret` | 5×3 | 10 lines | 10 | 87.5–114.5 (27) | pays left, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 95.49 (95.00) | on |
| The Magic Goblet | `ctinteractive/themagicgoblet` | 5×3 | clusters/other | 12 | 82.0–109.5 (48) | cluster pays, multipliers, scatters | 96.49 (95.04) | on |
| The Mighty Aztecs | `ctinteractive/themightyaztecs` | 5×3 | 20 lines | 20 | 83.8–110.7 (19) | pays left, line select, scatters, wilds | 93.79 (95.06) | on |
| The New Queen of Fruits | `ctinteractive/thenewqueenoffruits` | 5×3 | clusters/other | 40 | 83.5–114.3 (26) | cluster pays, cascade, scatters, wilds | 94.63 (95.03) | on |
| The Power of Ankh | `ctinteractive/thepowerofankh` | 5×3 | 25 lines | 25 | 87.3–112.5 (25) | pays left, line select, free games retrigger once, free games own reels, scatters, wilds | 93.50 (95.03) | on |
| The Power of Ramesses | `ctinteractive/thepoweroframesses` | 5×3 | 30 lines | 30 | 83.2–110.7 (18) | pays left, line select, free games retrigger, free games own reels, scatters, wilds | 95.40 (95.07) | on |
| The Temple of Astarta | `ctinteractive/thetempleofastarta` | 5×3 | 25 lines | 25 | 79.0–111.2 (20) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 96.04 (95.07) | on |
| Tibetan Song | `ctinteractive/tibetansong` | 5×3 | 25 lines | 25 | 87.6–115.5 (21) | pays left, line select, free games retrigger, scatters, wilds | 95.16 (95.14) | on |
| Treasure Chase | `ctinteractive/treasurechase` | 5×3 | 20 lines | 20 | 89.9–113.1 (24) | pays left, cascade, cascade features, scatters, wilds | 94.66 (95.01) | on |
| Treasure Hill | `ctinteractive/treasurehill` | 5×4 | 50 lines | 50 | 84.3–110.0 (18) | pays left, line select, free games retrigger, scatters, wilds | 95.39 (94.94) | on |
| Treasure Kingdom | `ctinteractive/treasurekingdom` | 5×3 | 9 lines | 9 | 85.5–115.1 (27) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.24 (95.00) | on |
| Tropic Dancer | `ctinteractive/tropicdancer` | 5×3 | 25 lines | 25 | 89.6–112.5 (17) | pays left, line select, free games retrigger, free games multipliers, stacked scatters, wilds | 96.61 (94.54) | on |
| Viking's Fun | `ctinteractive/vikingsfun` | 5×3 | 25 lines | 25 | 86.6–110.4 (23) | pays left, line select, free games retrigger, scatters, wilds, symbols turn wild | 93.76 (95.11) | on |
| Wet and Juicy | `ctinteractive/wetandjuicy` | 5×4 | 40 lines | 40 | 86.4–109.8 (17) | pays left, line select, scatters, wilds | 97.97 (95.13) | on |
| Wild Clover | `ctinteractive/wildclover` | 5×3 | 20 lines | 20 | 83.8–110.7 (19) | pays left, line select, scatters, wilds | 95.23 (95.06) | on |
| Wild Hills | `ctinteractive/wildhills` | 5×3 | 10 lines | 10 | 87.5–114.5 (27) | pays left, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 94.49 (95.00) | on |
| Win Feast | `ctinteractive/winfeast` | 5×3 | 20 lines | 20 | 89.9–113.1 (24) | pays left, cascade, cascade features, scatters, wilds | 95.25 (95.01) | on |
| Win Storm | `ctinteractive/winstorm` | 5×3 | 30 lines | 30 | 84.0–111.3 (31) | pays left, cascade, scatters, wilds | 94.53 (95.02) | on |
| Wonder 7's | `ctinteractive/wonder7s` | 5×3 | 5 lines | 5 | 86.1–111.5 (26) | pays left, line select, scatters, reel wilds | 94.18 (95.06) | on |

## Novomatic

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| African Simba | `novomatic/africansimba` | 5×3 | 243 ways | 25 | 85.2–110.1 (22) | pays left, free games retrigger, free games multipliers, scatters, wilds | 95.77 (95.59) | on |
| Age of Heroes | `novomatic/ageofheroes` | 5×3 | 5 lines | 5 | 93.8–99.1 (3) | pays left, scatters | 96.24 (95.66) | on |
| Always American | `novomatic/alwaysamerican` | 3×3 | 5 lines | 5 | 87.3–110.1 (18) | line select | 94.78 (95.50) | on |
| Always Hot | `novomatic/alwayshot` | 3×3 | 5 lines | 5 | 87.3–110.1 (18) | line select | 93.23 (95.50) | on |
| Always Hot Deluxe | `novomatic/alwayshotdeluxe` | 3×3 | 5 lines | 5 | 87.3–110.1 (18) | line select | 94.88 (95.50) | on |
| Angry Birds | `novomatic/angrybirds` | 5×3 | 10 lines | 10 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 94.59 (95.11) | on |
| Attila | `novomatic/attila` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.18 (95.13) | on |
| Banana Splash | `novomatic/bananasplash` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.58 (95.13) | on |
| Bananas Go Bahamas | `novomatic/bananasgobahamas` | 5×3 | 9 lines | 9 | 90.1–175.3 (7) | pays left, line select, free games retrigger, free games own reels, scatters, wilds, wild multipliers | 94.64 (95.36) | on |
| Beetle Mania | `novomatic/beetlemania` | 5×3 | 9 lines | 9 | 87.3–105.5 (16) | pays left, line select, free games, free games own reels, scatters, wilds | 105.83 (95.19) | **off** |
| Beetle Mania Deluxe | `novomatic/beetlemaniadeluxe` | 5×3 | 10 lines | 10 | 87.3–105.5 (16) | pays left, line select, free games, free games own reels, scatters, wilds | 104.71 (95.19) | **off** |
| Book of Ra | `novomatic/bookofra` | 5×3 | 9 lines | 9 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 94.52 (95.11) | on |
| Book of Ra Deluxe | `novomatic/bookofradeluxe` | 5×3 | 10 lines | 10 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 93.91 (95.11) | on |
| Cash Farm | `novomatic/cashfarm` | 5×3 | 25 lines | 25 | 85.3–110.2 (12) | pays left, cascade, free games retrigger, scatters, wilds | 65.45 (94.54) | **off** |
| Chicago | `novomatic/chicago` | 5×3 | 20 lines | 20 | 83.5–112.5 (11) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 94.43 (95.08) | on |
| Cold Spell | `novomatic/coldspell` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 97.03 (95.13) | on |
| Columbus | `novomatic/columbus` | 5×3 | 9 lines | 9 | 85.1–143.4 (9) | pays left, line select, free games retrigger, free games own reels, scatters, wilds | 97.18 (95.02) | on |
| Columbus Deluxe | `novomatic/columbusdeluxe` | 5×3 | 10 lines | 10 | 85.1–143.4 (9) | pays left, line select, free games retrigger, free games own reels, scatters, wilds | 95.98 (95.02) | on |
| Diamond 7 | `novomatic/diamond7` | 5×3 | 5 lines | 5 | 93.8–99.1 (3) | pays left, scatters | 96.69 (95.66) | on |
| Dolphins Pearl | `novomatic/dolphinspearl` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 97.76 (95.13) | on |
| Dolphins Pearl Deluxe | `novomatic/dolphinspearldeluxe` | 5×3 | 10 lines | 10 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 95.23 (95.13) | on |
| Down Under | `novomatic/downunder` | 5×3 | 9 lines | 9 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 95.19 (95.11) | on |
| Dragon's Deep | `novomatic/dragonsdeep` | 5×3 | 25 lines | 25 | 84.4–110.8 (19) | pays left, line select, free games retrigger, scatters, wilds, symbols turn wild | 94.40 (95.09) | on |
| Dynasty Of Ming | `novomatic/dynastyofming` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.03 (95.13) | on |
| Dynasty of Ra | `novomatic/dynastyofra` | 5×3 | 10 lines | 10 | 83.6–110.8 (20) | pays left, line select, free games retrigger, free games own reels, wild scatters | 96.55 (95.19) | on |
| Fairy Queen | `novomatic/fairyqueen` | 5×3 | 10 lines | 10 | 85.2–114.6 (15) | pays left, line select, free games retrigger, free games own reels, scatters, wilds | 97.53 (94.59) | on |
| Faust | `novomatic/faust` | 5×3 | 10 lines | 10 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 96.09 (95.11) | on |
| Flame Dancer | `novomatic/flamedancer` | 5×3 | 20 lines | 20 | 86.9–110.4 (17) | pays left, line select, free games retrigger, scatters, wilds, reel wilds, symbols turn wild | 94.27 (95.03) | on |
| Fruit Sensation | `novomatic/fruitsensation` | 5×3 | 10 lines | 10 | 92.2–111.0 (16) | pays left | 94.52 (95.17) | on |
| Fruitilicious | `novomatic/fruitilicious` | 5×3 | 5 lines | 5 | 92.7–122.6 (18) | pays left, pays both ways | 95.05 (94.76) | on |
| Fruits 'n Royals | `novomatic/fruitsnroyals` | 5×3 | 5 lines | 5 | 93.8–99.1 (3) | pays left, scatters | 96.23 (95.66) | on |
| Gate of Ra Deluxe | `novomatic/gateofradeluxe` | 5×3 | 10 lines | 10 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 96.20 (95.11) | on |
| God of Sun | `novomatic/godofsun` | 5×3 | 9 lines | 9 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 93.70 (95.11) | on |
| Golden Prophecies | `novomatic/goldenprophecies` | 5×3 | 10 lines | 10 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 97.33 (95.11) | on |
| Gryphons Gold | `novomatic/gryphonsgold` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.70 (95.13) | on |
| Gryphons Gold Deluxe | `novomatic/gryphonsgolddeluxe` | 5×3 | 10 lines | 10 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 92.46 (95.13) | on |
| Helena | `novomatic/helena` | 5×3 | 10 lines | 10 | 87.7–114.5 (18) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.32 (95.10) | on |
| Holmes and Watson Deluxe | `novomatic/holmesandwatsondeluxe` | 5×3 | 10 lines | 10 | 85.1–143.4 (9) | pays left, line select, free games retrigger, free games own reels, scatters, wilds | 96.33 (95.02) | on |
| Hot Chance | `novomatic/hotchance` | 3×3 | 5 lines | 5 | 88.2–110.6 (7) | line select, full screen multiplier | 95.68 (95.66) | on |
| Hot Cubes | `novomatic/hotcubes` | 5×3 | 5 lines | 5 | 93.8–99.1 (3) | pays left, scatters | 96.04 (95.66) | on |
| Hot Target | `novomatic/hottarget` | 5×3 | 9 lines | 9 | 87.3–105.5 (16) | pays left, line select, free games, free games own reels, scatters, wilds | 105.35 (95.19) | **off** |
| Inferno | `novomatic/inferno` | 5×3 | 5 lines | 5 | 92.4–110.3 (18) | pays left, scatters | 96.31 (95.08) | on |
| Jaguar Moon | `novomatic/jaguarmoon` | 5×3 | 243 ways | 10 | 87.8–113.5 (24) | pays left, free games retrigger, free games own reels, free games multipliers, scatters, wilds | 93.35 (94.76) | on |
| Jewels | `novomatic/jewels` | 5×3 | 9 lines | 9 | 88.9–117.8 (9) | pays left, pays both ways, pays anywhere, line select | 94.06 (95.02) | on |
| Jewels 4 All | `novomatic/jewels4all` | 5×3 | 9 lines | 9 | 88.1–110.1 (13) | pays left, pays both ways, pays anywhere, line select, big wilds | 3670.21 (94.98) | **off** |
| Joker Dolphin | `novomatic/jokerdolphin` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 95.54 (95.13) | on |
| Just Fruits | `novomatic/justfruits` | 5×3 | 9 lines | 9 | 86.5–122.8 (35) | pays left, pays both ways, pays anywhere, line select, scatters | 95.08 (95.06) | on |
| Just Jewels | `novomatic/justjewels` | 5×3 | 9 lines | 9 | 86.5–122.8 (35) | pays left, pays both ways, pays anywhere, line select, scatters | 95.07 (95.06) | on |
| Just Jewels Deluxe | `novomatic/justjewelsdeluxe` | 5×3 | 10 lines | 10 | 86.5–122.8 (35) | pays left, pays both ways, pays anywhere, line select, scatters | 95.52 (95.06) | on |
| Katana | `novomatic/katana` | 5×3 | 20 lines | 20 | 87.3–100.0 (7) | pays left, line select, free games retrigger, free games own reels, scatters, wilds, reel wilds | 93.55 (95.39) | on |
| King Of Cards | `novomatic/kingofcards` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.74 (95.13) | on |
| King's Jester | `novomatic/kingsjester` | 5×3 | 10 lines | 10 | 86.9–111.3 (22) | pays left, line select, jackpot, free games retrigger, scatters, wilds, wild multipliers | 93.27 (95.00) | on |
| Lord of the Ocean | `novomatic/lordoftheocean` | 5×3 | 10 lines | 10 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 93.40 (95.11) | on |
| Lovely Mermaid | `novomatic/lovelymermaid` | 5×4 | 40 lines | 40 | 81.3–106.4 (21) | pays left, line select, jackpot, free games retrigger, scatters, wilds | 95.43 (95.29) | on |
| Lucky Lady's Charm | `novomatic/luckyladyscharm` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.06 (95.13) | on |
| Lucky Lady's Charm Deluxe | `novomatic/luckyladyscharmdeluxe` | 5×3 | 10 lines | 10 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 98.05 (95.13) | on |
| Marco Polo | `novomatic/marcopolo` | 5×3 | 9 lines | 9 | 85.1–143.4 (9) | pays left, line select, free games retrigger, free games own reels, scatters, wilds | 95.36 (95.02) | on |
| Mega Joker | `novomatic/megajoker` | 5×4 | 40 lines | 40 | 86.2–111.6 (31) | pays left, scatters, wilds | 93.67 (94.78) | on |
| Mermaid's Pearl | `novomatic/mermaidspearl` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.65 (95.13) | on |
| Oliver's Bar | `novomatic/oliversbar` | 5×3 | 9 lines | 9 | 88.8–108.9 (20) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 95.54 (94.86) | on |
| Pharaoh's Gold II | `novomatic/pharaohsgoldii` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.85 (95.13) | on |
| Pharaoh's Gold III | `novomatic/pharaohsgoldiii` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 93.50 (95.13) | on |
| Plenty of Fruit 20 hot | `novomatic/plentyoffruit20hot` | 5×3 | 20 lines | 20 | 85.9–110.1 (22) | pays left, line select, scatters, wilds | 94.01 (95.15) | on |
| Plenty of Jewels 20 hot | `novomatic/plentyofjewels20hot` | 5×3 | 20 lines | 20 | 85.9–110.1 (22) | pays left, line select, scatters, wilds | 94.65 (95.15) | on |
| Plenty on Twenty | `novomatic/plentyontwenty` | 5×3 | 20 lines | 20 | 88.7–110.9 (16) | pays left, line select, scatters, wilds | 97.02 (95.04) | on |
| Polar Fox | `novomatic/polarfox` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 95.28 (95.13) | on |
| Power Stars | `novomatic/powerstars` | 5×3 | 10 lines | 10 | 86.0–115.0 (18) | pays left, pays both ways, line select, wilds | 103036.10 (95.03) | **off** |
| Ramses II | `novomatic/ramsesii` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.48 (95.13) | on |
| Roaring Forties | `novomatic/roaringforties` | 5×4 | 40 lines | 40 | 88.6–111.2 (7) | pays left, line select, scatters, wilds | 92.97 (95.42) | on |
| Roaring Wilds | `novomatic/roaringwilds` | 5×4 | 40 lines | 40 | 88.6–111.2 (7) | pays left, line select, scatters, wilds | 96.40 (95.42) | on |
| Royal Dynasty | `novomatic/royaldynasty` | 5×3 | 20 lines | 20 | 86.2–112.4 (18) | pays left, line select, free games retrigger, free games own reels, scatters, wilds, symbols turn wild, wild multipliers | 95.62 (95.09) | on |
| Royal Jewels | `novomatic/royaljewels` | 5×3 | 10 lines | 10 | 86.5–122.8 (35) | pays left, pays both ways, pays anywhere, line select, scatters | 97.05 (95.06) | on |
| Royal Treasures | `novomatic/royaltreasures` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.34 (95.13) | on |
| Secret Elixir | `novomatic/secretelixir` | 5×3 | 10 lines | 10 | 84.0–109.3 (21) | pays left, line select, free games retrigger, free games own reels, multipliers, free games multipliers, scatters, wilds | 95.00 (95.00) | on |
| Secret Forest | `novomatic/secretforest` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.56 (95.13) | on |
| Sizzling Hot | `novomatic/sizzlinghot` | 5×3 | 5 lines | 5 | 93.8–99.1 (3) | pays left, scatters | 95.83 (95.66) | on |
| Sizzling Hot Deluxe | `novomatic/sizzlinghotdeluxe` | 5×3 | 5 lines | 5 | 93.8–99.1 (3) | pays left, scatters | 95.37 (95.66) | on |
| The Money Game | `novomatic/themoneygame` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 93.47 (95.13) | on |
| The Money Game Deluxe | `novomatic/themoneygamedeluxe` | 5×3 | 10 lines | 10 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.12 (95.13) | on |
| The Real King Gold Records | `novomatic/therealkinggoldrecords` | 5×3 | 10 lines | 10 | 90.0–176.8 (7) | pays left, line select, free games retrigger, free games own reels, wild scatters | 97.22 (95.11) | on |
| Ultra Gems | `novomatic/ultragems` | 3×3 | 5 lines | 5 | 88.2–110.6 (7) | line select, full screen multiplier | 94.02 (95.66) | on |
| Ultra Hot | `novomatic/ultrahot` | 3×3 | 5 lines | 5 | 88.2–110.6 (7) | line select, full screen multiplier | 96.54 (95.66) | on |
| Ultra Hot Deluxe | `novomatic/ultrahotdeluxe` | 3×3 | 5 lines | 5 | 88.2–110.6 (7) | line select, full screen multiplier | 96.00 (95.66) | on |
| Ultra Sevens | `novomatic/ultrasevens` | 5×4 | 40 lines | 40 | 89.7–107.0 (13) | pays left, jackpot, scatters | 92.65 (95.09) | on |
| Unicorn Magic | `novomatic/unicornmagic` | 5×3 | 9 lines | 9 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 95.13 (95.13) | on |
| Wild Horses | `novomatic/wildhorses` | 5×4 | 50 lines | 50 | 87.5–109.9 (22) | pays left, line select, pick mode, free games retrigger, free games own reels, scatters, wilds | 89.17 (95.00) | **off** |

## AGT

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| 100 Jokers | `agt/100jokers` | 5×4 | 100 lines | 100 | 87.6–110.6 (18) | pays left, line select, scatters, wilds | 95.23 (95.64) | on |
| 100 Shining Stars | `agt/100shiningstars` | 5×4 | 100 lines | 100 | 87.8–111.1 (15) | pays left, line select, scatters, reel wilds | 92.79 (94.95) | on |
| 40 Bigfoot | `agt/40bigfoot` | 5×4 | 40 lines | 40 | 87.6–110.6 (18) | pays left, line select, scatters, wilds | 93.91 (95.64) | on |
| 5 Hot Hot Hot | `agt/5hothothot` | 3×3 | 5 lines | 5 | 80.7–106.0 (17) | free games retrigger, scatters, wilds | 93.44 (95.83) | on |
| 50 Apples' Shine | `agt/50applesshine` | 5×4 | 50 lines | 50 | 87.8–111.1 (15) | pays left, line select, scatters, reel wilds | 93.87 (94.95) | on |
| 50 Gems | `agt/50gems` | 5×4 | 50 lines | 50 | 90.3–111.6 (16) | pays left, line select, scatters, wilds | 92.89 (95.15) | on |
| 50 Happy Santa | `agt/50happysanta` | 5×4 | 50 lines | 50 | 87.6–110.6 (18) | pays left, line select, scatters, wilds | 95.09 (95.64) | on |
| AI | `agt/ai` | 5×3 | 15 lines | 15 | 82.9–113.9 (20) | pays left, line select, free games retrigger, wild scatters | 93.76 (95.17) | on |
| Aladdin | `agt/aladdin` | 5×4 | 100 lines | 100 | 87.7–110.8 (19) | pays left, line select, free games retrigger, scatters, wilds | 94.83 (94.85) | on |
| Anonymous | `agt/anonymous` | 5×3 | 20 lines | 20 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 93.51 (95.13) | on |
| Apples' Shine | `agt/applesshine` | 5×3 | 20 lines | 20 | 86.4–110.6 (13) | pays left, scatters, reel wilds | 95.63 (95.46) | on |
| Aquaman | `agt/aquaman` | 5×3 | 10 lines | 10 | 83.0–109.3 (25) | pays left, line select, free games, scatters, wilds, big symbols | 92.94 (95.01) | on |
| Arabian Nights | `agt/arabiannights` | 5×3 | 10 lines | 10 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 95.54 (95.13) | on |
| Arabian Nights 2 | `agt/arabiannights2` | 5×4 | 100 lines | 100 | 87.7–110.8 (19) | pays left, line select, free games retrigger, scatters, wilds | 93.98 (94.85) | on |
| Around The World | `agt/aroundtheworld` | 5×4 | 40 lines | 40 | 81.6–112.0 (17) | pays left, line select, free games retrigger, stacked scatters, wilds | 93.78 (94.58) | on |
| Big Five | `agt/bigfive` | 5×3 | 15 lines | 15 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 92.97 (95.13) | on |
| Bigfoot | `agt/bigfoot` | 5×3 | 20 lines | 20 | 86.5–109.8 (16) | pays left, line select, scatters, wilds | 93.41 (94.96) | on |
| Bitcoin | `agt/bitcoin` | 5×3 | 15 lines | 15 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 98.08 (95.13) | on |
| Book of Set | `agt/bookofset` | 5×3 | 30 lines | 30 | 82.9–113.9 (20) | pays left, line select, free games retrigger, wild scatters | 95.99 (95.17) | on |
| Casino | `agt/casino` | 5×4 | 100 lines | 100 | 87.7–110.8 (19) | pays left, line select, free games retrigger, scatters, wilds | 94.75 (94.85) | on |
| Cherry Hot | `agt/cherryhot` | 5×3 | 5 lines | 5 | 87.5–112.0 (21) | pays left, line select, scatters | 92.86 (95.06) | on |
| Crown | `agt/crown` | 5×4 | 100 lines | 100 | 87.7–110.8 (19) | pays left, line select, free games retrigger, scatters, wilds | 94.02 (94.85) | on |
| Double Hot | `agt/doublehot` | 3×3 | 27 lines | 27 | 84.1–112.0 (18) | full screen multiplier | 95.21 (95.03) | on |
| Double Ice | `agt/doubleice` | 3×3 | 27 lines | 27 | 84.1–112.0 (18) | full screen multiplier | 95.19 (95.03) | on |
| Egypt | `agt/egypt` | 5×3 | 15 lines | 15 | 87.2–111.4 (21) | pays left, line select, multipliers, scatters, wilds | 94.98 (95.10) | on |
| Extra Spin | `agt/extraspin` | 5×3 | 10 lines | 10 | 87.8–110.7 (25) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 95.18 (95.10) | on |
| Extra Spin II | `agt/extraspinii` | 5×4 | 40 lines | 40 | 83.3–109.6 (21) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 94.83 (95.08) | on |
| Extra Spin III | `agt/extraspiniii` | 5×3 | 10 lines | 10 | 85.5–110.2 (29) | pays left, line select, free games retrigger, scatters, wilds | 94.26 (94.96) | on |
| Firefighters | `agt/firefighters` | 5×3 | 15 lines | 15 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 97.78 (95.13) | on |
| Fruit Queen | `agt/fruitqueen` | 5×6 | 18 lines | 18 | 90.0–109.8 (13) | pays left, line select, scatters, wilds | 96.02 (95.30) | on |
| Gems | `agt/gems` | 5×3 | 20 lines | 20 | 89.7–111.7 (18) | pays left, line select, scatters, wilds | 95.16 (95.49) | on |
| Grand Theft | `agt/grandtheft` | 5×3 | 20 lines | 20 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.01 (95.13) | on |
| Green Hot | `agt/greenhot` | 5×3 | 5 lines | 5 | 86.4–110.6 (13) | pays left, scatters, reel wilds | 95.94 (95.46) | on |
| Halloween | `agt/halloween` | 3×3 | 10 lines | 10 | 83.8–113.8 (28) |  | 93.13 (94.95) | on |
| Happy Santa | `agt/happysanta` | 5×3 | 20 lines | 20 | 86.5–109.8 (16) | pays left, line select, scatters, wilds | 94.92 (94.96) | on |
| Hot Clover | `agt/hotclover` | 5×4 | 100 lines | 100 | 83.8–112.4 (19) | pays left, scatters, reel wilds | 92.50 (94.52) | on |
| Ice Fruits | `agt/icefruits` | 5×3 | 20 lines | 20 | 88.9–112.2 (24) | pays left, scatters, wilds | 95.51 (95.20) | on |
| Ice Fruits 6 reels | `agt/icefruits6reels` | 6×3 | 20 lines | 20 | 86.5–113.5 (22) | pays left, line select, scatters, wilds | 95.40 (95.01) | on |
| Ice Ice Ice | `agt/iceiceice` | 3×3 | 10 lines | 10 | 80.7–106.0 (17) | free games retrigger, scatters, wilds | 93.06 (95.83) | on |
| Ice Queen | `agt/icequeen` | 5×3 | 10 lines | 10 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.86 (95.13) | on |
| Infinity Gems | `agt/infinitygems` | 5×3 | 20 lines | 20 | 86.1–111.9 (19) | pays left, line select, free games retrigger, free games own reels, scatters, wilds, wild multipliers | 95.92 (95.00) | on |
| Jokers | `agt/jokers` | 5×3 | 20 lines | 20 | 86.5–109.8 (16) | pays left, line select, scatters, wilds | 95.40 (94.96) | on |
| Live Fruits | `agt/livefruits` | 5×3 | 20 lines | 20 | 94.6–105.5 (9) | pays left, line select, scatters | 94.03 (94.61) | on |
| Lucky Slot | `agt/luckyslot` | 5×3 | 10 lines | 10 | 90.1–110.9 (23) | pays left, line select, scatters, reel wilds | 93.50 (95.01) | on |
| Mega Shine | `agt/megashine` | 5×3 | 30 lines | 30 | 88.9–112.2 (24) | pays left, scatters, wilds | 94.60 (95.20) | on |
| Merry Christmas | `agt/merrychristmas` | 3×3 | 5 lines | 5 | 87.2–112.3 (35) | free games retrigger, scatters, wilds | 95.71 (95.05) | on |
| Panda | `agt/panda` | 3×3 | 27 lines | 27 | 87.8–112.0 (20) | free games retrigger, scatters, wilds | 95.80 (95.15) | on |
| Pharaoh II | `agt/pharaohii` | 5×3 | 30 lines | 30 | 82.9–113.9 (20) | pays left, line select, free games retrigger, wild scatters | 96.76 (95.17) | on |
| Pirates Gold | `agt/piratesgold` | 5×3 | 20 lines | 20 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 93.55 (95.13) | on |
| Red Crown | `agt/redcrown` | 5×4 | 100 lines | 100 | 87.8–111.1 (15) | pays left, line select, scatters, reel wilds | 95.11 (94.95) | on |
| STALKER | `agt/stalker` | 5×3 | 10 lines | 10 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.83 (95.13) | on |
| Santa | `agt/santa` | 4×4 | 10 lines | 10 | 86.0–111.1 (22) | pays left, line select, free games retrigger, scatters, wilds | 94.11 (94.94) | on |
| Seven Hot | `agt/sevenhot` | 5×3 | 20 lines | 20 | 94.6–105.5 (9) | pays left, line select, scatters | 95.33 (94.61) | on |
| Shining Stars | `agt/shiningstars` | 5×3 | 10 lines | 10 | 86.4–110.6 (13) | pays left, scatters, reel wilds | 95.67 (95.46) | on |
| Sun City | `agt/suncity` | 5×3 | 30 lines | 30 | 85.0–112.6 (21) | pays left, line select, free games, free games own reels, scatters, wilds | 95.37 (94.92) | on |
| Tesla | `agt/tesla` | 5×3 | 15 lines | 15 | 82.9–113.9 (20) | pays left, line select, free games retrigger, wild scatters | 95.22 (95.17) | on |
| The Leprechaun | `agt/theleprechaun` | 5×3 | 10 lines | 10 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.61 (95.13) | on |
| Time Machine II | `agt/timemachineii` | 5×3 | 15 lines | 15 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.59 (95.13) | on |
| Tropic Hot | `agt/tropichot` | 3×3 | 5 lines | 5 | 87.3–110.1 (18) | line select | 93.74 (95.50) | on |
| Valentine's Day | `agt/valentinesday` | 5×3 | 5 lines | 5 | 89.2–109.9 (20) | pays left, full screen multiplier, scatters | 96.25 (95.62) | on |
| Valkyrie | `agt/valkyrie` | 5×3 | 30 lines | 30 | 83.0–109.3 (25) | pays left, line select, free games, scatters, wilds, big symbols | 92.39 (95.01) | on |
| Wild West | `agt/wildwest` | 5×4 | 100 lines | 100 | 87.7–110.8 (19) | pays left, line select, free games retrigger, scatters, wilds | 94.61 (94.85) | on |
| Wizard | `agt/wizard` | 5×4 | 50 lines | 50 | 81.6–112.0 (17) | pays left, line select, free games retrigger, stacked scatters, wilds | 96.88 (94.58) | on |
| Zeus | `agt/zeus` | 4×4 | 10 lines | 10 | 91.0–116.1 (15) | pays left, line select, free games retrigger, scatters, wilds | 96.70 (95.07) | on |

## NetEnt

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| Arabian Nights | `netent/arabiannights` | 5×3 | 10 lines | 10 | 85.3–106.9 (11) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 93.45 (95.21) | on |
| Diamond Dogs | `netent/diamonddogs` | 5×3 | 25 lines | 25 | 85.8–112.4 (24) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds | 96.01 (94.75) | on |
| Excalibur | `netent/excalibur` | 5×3 | 20 lines | 20 | 87.8–110.3 (10) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 93.81 (95.18) | on |
| Flowers | `netent/flowers` | 5×3 | 30 lines | 30 | 91.2–110.9 (14) | pays left, line select, free games retrigger, free games own reels, free games multipliers, wilds | 94.77 (94.53) | on |
| Fruit Shop | `netent/fruitshop` | 5×3 | 15 lines | 15 | 85.0–111.1 (17) | pays left, free games retrigger, free games multipliers, wilds, wild multipliers | 93.48 (94.92) | on |
| Funky Seventies | `netent/funkyseventies` | 5×4 | 40 lines | 40 | 86.3–109.5 (15) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 94.23 (95.52) | on |
| Geisha Wonders | `netent/geishawonders` | 5×3 | 30 lines | 30 | 80.0–113.8 (15) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 95.53 (94.52) | on |
| Gonzo's Quest | `netent/gonzosquest` | 5×3 | 20 lines | 20 | 87.1–112.2 (16) | pays left, cascade, cascade features, free games retrigger, free games multipliers, scatters, wilds | 92.61 (95.31) | on |
| Groovy Sixties | `netent/groovysixties` | 5×4 | 40 lines | 40 | 86.3–109.5 (15) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 96.59 (95.52) | on |
| Pandora's Box | `netent/pandorasbox` | 5×3 | 20 lines | 20 | 87.8–110.3 (10) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 95.60 (95.18) | on |
| Piggy Riches | `netent/piggyriches` | 5×3 | 15 lines | 15 | 88.0–110.5 (14) | pays left, line select, pick mode, free games, free games multipliers, scatters, wilds, wild multipliers | 96.71 (95.27) | on |
| Reel Steal | `netent/reelsteal` | 5×3 | 9 lines | 9 | 87.1–111.6 (13) | pays left, line select, free games, free games multipliers, scatters, wilds, wild multipliers | 93.93 (95.33) | on |
| Secret Of Horus | `netent/secretofhorus` | 5×3 | 20 lines | 20 | 84.0–111.8 (16) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 94.69 (95.08) | on |
| Simsalabim | `netent/simsalabim` | 5×3 | 25 lines | 25 | 84.5–108.0 (23) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds | 96.91 (95.16) | on |
| Spellcast | `netent/spellcast` | 5×3 | 20 lines | 20 | 84.0–111.8 (16) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 94.56 (95.08) | on |
| Super Eighties | `netent/supereighties` | 5×4 | 40 lines | 40 | 86.3–109.5 (15) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 94.44 (95.52) | on |
| Thrill Spin | `netent/thrillspin` | 5×3 | 15 lines | 15 | 85.8–111.1 (17) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 94.73 (95.13) | on |
| Tiki Wonders | `netent/tikiwonders` | 5×3 | 30 lines | 30 | 80.0–113.8 (15) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds | 94.55 (94.52) | on |
| Trolls | `netent/trolls` | 5×3 | 20 lines | 20 | 87.8–110.3 (10) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 97.14 (95.18) | on |
| Viking's Treasure | `netent/vikingstreasure` | 5×3 | 15 lines | 15 | 85.8–111.1 (17) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 93.98 (95.13) | on |
| Voodoo Vibes | `netent/voodoovibes` | 5×3 | 25 lines | 25 | 85.8–112.4 (24) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds | 96.15 (94.75) | on |
| Wild Witches | `netent/wildwitches` | 5×3 | 20 lines | 20 | 87.8–110.3 (10) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 94.80 (95.18) | on |

## Playtech

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| Captain's Treasure | `playtech/captainstreasure` | 5×3 | 9 lines | 9 | 88.3–110.1 (17) | pays left, pays both ways, line select, scatters, wilds, wild multipliers | 94.12 (95.00) | on |
| Desert Treasure | `playtech/deserttreasure` | 5×3 | 9 lines | 9 | 84.2–111.6 (14) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds | 95.41 (94.84) | on |
| Dolphin Reef | `playtech/dolphinreef` | 5×3 | 20 lines | 20 | 82.5–115.5 (23) | pays left, line select, free games, scatters, wilds, reel wilds | 92.97 (95.28) | on |
| Golden Tour | `playtech/goldentour` | 5×3 | 5 lines | 5 | 148.2–148.2 (1) | pays left, pays both ways, line select, scatters, wilds | 146.96 (148.24) | on |
| Great Blue | `playtech/greatblue` | 5×3 | 25 lines | 25 | 85.1–104.5 (16) | pays left, line select, pick mode, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 853.90 (95.07) | **off** |
| Irish Luck | `playtech/irishluck` | 5×3 | 30 lines | 30 | 85.1–104.5 (16) | pays left, line select, pick mode, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 867.23 (95.07) | **off** |
| Panther Moon | `playtech/panthermoon` | 5×3 | 15 lines | 15 | 86.5–141.5 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 94.79 (95.40) | on |
| Safari Heat | `playtech/safariheat` | 5×3 | 15 lines | 15 | 86.5–141.5 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 97.03 (95.40) | on |

## IGT

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| Cleopatra | `igt/cleopatra` | 5×3 | 20 lines | 20 | 86.3–112.9 (18) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 93.05 (95.01) | on |
| Double Diamond | `igt/doublediamond` | 3×3 | 1 lines | 1 | 89.0–112.1 (19) | mixed pays, wilds, wild multipliers | 95.25 (95.11) | on |
| Rich Girl | `igt/richgirl` | 5×3 | 9 lines | 9 | 85.8–113.1 (19) | pays left, line select, free games retrigger, free games own reels, scatters, wilds, wild multipliers | 95.32 (94.52) | on |
| Triple Diamond | `igt/triplediamond` | 3×3 | 9 lines | 9 | 88.6–112.5 (18) | line select, mixed pays, wilds, wild multipliers | 94.37 (94.84) | on |
| Wolf Run | `igt/wolfrun` | 5×4 | 40 lines | 40 | 87.7–111.1 (14) | pays left, line select, free games retrigger, free games own reels, scatters, wilds | 95.79 (95.35) | on |

## Megajack

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| Aztec Gold | `megajack/aztecgold` | 5×3 | 21 lines | 21 | 87.6–112.4 (42) | pays left, line select, jackpot, scatters, reel wilds | 96.08 (95.01) | on |
| Champagne | `megajack/champagne` | 5×3 | 21 lines | 21 | 96.0–96.0 (1) | pays left, line select, jackpot, free games retrigger, free games multipliers, scatters, wilds | 96.95 (95.96) | on |
| Slotopol | `megajack/slotopol` | 5×3 | 21 lines | 21 | 99.8–99.8 (1) | pays left, line select, jackpot, scatters, wilds, wild multipliers | 99.35 (99.76) | on |
| Slotopol Deluxe | `megajack/slotopoldeluxe` | 5×3 | 21 lines | 21 | 95.7–103.6 (4) | pays left, line select, jackpot, scatters, wilds, wild multipliers | 95.04 (95.68) | on |

## Aristocrat

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| Dolphin Treasure | `aristocrat/dolphintreasure` | 5×3 | 10 lines | 10 | 86.3–140.8 (9) | pays left, line select, free games retrigger, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 96.39 (95.13) | on |
| Indian Cash Catcher | `aristocrat/indiancashcatcher` | 5×3 | 243 ways | 25 | 86.8–115.1 (20) | pays left, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 92.29 (94.81) | on |
| Redroo | `aristocrat/redroo` | 5×4 | 1024 ways | 50 | 92.5–108.7 (16) | pays left, free games retrigger, scatters, wilds, wild multipliers | 94.88 (94.80) | on |

## BetSoft

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| 2 Million B.C. | `betsoft/2millionbc` | 5×3 | 30 lines | 30 | 88.8–114.1 (8) | pays left, line select, free games retrigger, free games own reels, scatters | 115.56 (95.64) | **off** |
| At the Movies | `betsoft/atthemovies` | 5×3 | 25 lines | 25 | 93.2–100.4 (5) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 95.32 (95.22) | on |
| Sushi Bar | `betsoft/sushibar` | 5×3 | 25 lines | 25 | 93.2–100.4 (5) | pays left, line select, free games retrigger, free games multipliers, scatters, wilds, wild multipliers | 95.38 (95.22) | on |

## Play'n GO

| Game | ID | Grid | Lines / ways | Price | RTP sets | Features | Measured | Status |
|---|---|---|---|---:|---|---|---:|---|
| Cops'n'Robbers | `playngo/copsnrobbers` | 5×3 | 9 lines | 9 | 85.7–111.9 (12) | pays left, line select, free games, free games own reels, free games multipliers, scatters, wilds, wild multipliers | 95.74 (95.37) | on |
| Fire Joker | `playngo/firejoker` | 5×3 | 5 lines | 5 | 81.2–103.6 (8) | pays left, free games, scatters, big symbols | 94.68 (93.85) | on |
| Fortune Teller | `playngo/fortuneteller` | 5×3 | 20 lines | 20 | 84.9–125.9 (13) | pays left, line select, free games, scatters, wilds | 66.07 (94.74) | **off** |

## Switched off

These games pay far from their label when played; the same happens with the game object kept in memory as the
original server did, so the cause is in the game code (not reported upstream yet). An administrator can
switch one on in Casino → Slot games, but it would pay what the measurement shows.

| Game | Maker | Label | Measured |
|---|---|---:|---:|
| Cash Farm | Novomatic | 94.54 | 65.45 |
| Fortune Teller | Play'n GO | 94.74 | 66.07 |
| Magic Jewels | CT Interactive | 94.56 | 74.28 |
| Hyper Cuber | CT Interactive | 95.18 | 82.32 |
| Full of Treasures | CT Interactive | 95.05 | 87.96 |
| Smiley x Wild | CT Interactive | 95.05 | 88.44 |
| Chilli Bomb | CT Interactive | 95.82 | 88.85 |
| Wild Horses | Novomatic | 95.00 | 89.17 |
| King of Clovers | CT Interactive | 95.16 | 91.93 |
| Money Pipe | CT Interactive | 95.00 | 100.79 |
| Ice Rubies | CT Interactive | 95.26 | 101.32 |
| More Dragons | CT Interactive | 95.00 | 101.37 |
| Fire Dozen | CT Interactive | 95.26 | 101.42 |
| Colibri Wild | CT Interactive | 95.26 | 101.52 |
| Beetle Mania Deluxe | Novomatic | 95.19 | 104.71 |
| Hot Target | Novomatic | 95.19 | 105.35 |
| Beetle Mania | Novomatic | 95.19 | 105.83 |
| 2 Million B.C. | BetSoft | 95.64 | 115.56 |
| Great Blue | Playtech | 95.07 | 853.90 |
| Irish Luck | Playtech | 95.07 | 867.23 |
| Jewels 4 All | Novomatic | 94.98 | 3670.21 |
| Mighty Moon | CT Interactive | 95.76 | 45591.53 |
| Neon Bananas | CT Interactive | 95.76 | 45790.97 |
| Clover Wheel | CT Interactive | 95.76 | 45935.41 |
| Power Stars | Novomatic | 95.03 | 103036.10 |
