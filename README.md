# SkyWars Plugin for PocketMine-MP

Enterprise-level SkyWars plugin for PocketMine-MP API 5.x with queue system, map voting, and multiple game modes.

## Features

✨ **Multiple Game Modes**
- Solo (1v1v1...) - up to 12 players
- Duos (2v2v2...) - up to 16 players
- Squads (4v4v4...) - up to 16 players (teams of 4)

🎮 **Advanced Queue System**
- Separate queues for each game mode
- Automatic matchmaking
- 30-second countdown before game starts
- "Waiting for players" tips when below minimum
- Minimum 3 players to start

🗳️ **Map Voting**
- Players vote for their favorite map
- Most voted map wins
- Random selection if no votes
- Interactive voting UI with forms

⚙️ **Interactive Arena Configuration**
- Create arenas with `/sw create <name>`
- Interactive setup wizard with forms
- Set cage spawn points
- Configure chest locations
- Set spectator spawn
- Choose supported game modes per arena

💬 **Fully Customizable Messages**
- All messages in `messages.json`
- Support for placeholders
- Color code support
- Easy localization

🎯 **Complete Game Logic**
- 5-second cage countdown
- PvP protection during countdown
- Death/elimination handling
- Team support for Duos/Squads
- Win condition detection
- Spectator mode
- Kill tracking
- Game time limits

## Installation

1. Download the plugin
2. Place in your PocketMine-MP `plugins/` folder
3. Restart your server
4. Configure `config.yml` and `messages.json` as needed

## Quick Start

### Setting Up Your First Arena

1. **Create the arena**:
   ```
   /sw create skyisland
   ```

2. **Configure the arena** (form opens automatically):
   - Select "Add Cage" and stand where you want each cage
   - Repeat for all cages (minimum 4 required)
   - Select "Add Chest Location" for loot chest spawns (optional)
   - Select "Set Spectator Spawn" where eliminated players go
   - Select "Configure Game Modes" to choose Solo/Duos/Squads support
   - Select "Finish Setup" when done

3. **Set lobby spawn** (where queue waiting happens):
   ```
   /sw setlobby
   ```

4. **Players join** using:
   ```
   /sw join
   ```
   They'll see a form to select Solo/Duos/Squads

### Game Flow

1. Player runs `/sw join`
2. Selects game mode (Solo/Duos/Squads)
3. Teleported to queue lobby
4. Given vote item (paper) and leave item (bed)
5. Can vote for a map using the vote item
6. When 3+ players: 30-second countdown starts
7. Most voted map selected (or random if no votes)
8. Players teleported to cages
9. 5-second countdown
10. Game starts - fight to be last standing!
11. Winner announced, players returned to original location

## Commands

| Command | Description | Permission |
|---------|-------------|------------|
| `/sw join` | Open game mode selection | `skywars.command` |
| `/sw leave` | Leave queue or game | `skywars.command` |
| `/sw create <name>` | Create new arena | `skywars.admin` |
| `/sw edit <name>` | Edit existing arena | `skywars.admin` |
| `/sw delete <name>` | Delete arena | `skywars.admin` |
| `/sw list` | List all arenas | `skywars.admin` |
| `/sw setlobby` | Set queue lobby spawn | `skywars.admin` |

## Configuration

### config.yml

```yaml
# Lobby settings - where players wait in queue
lobby:
  world: "world"
  spawn:
    x: 0
    y: 100
    z: 0

# Game mode settings
game-modes:
  solo:
    enabled: true
    min-players: 3
    max-players: 12
    team-size: 1
  duos:
    enabled: true
    min-players: 3
    max-players: 16
    team-size: 2
  squads:
    enabled: true
    min-players: 3
    max-players: 16
    team-size: 4

# Game settings
game:
  lobby-countdown: 30      # Seconds in queue before game starts
  cage-countdown: 5        # Seconds in cage before game starts
  max-game-time: 600       # Max game time (0 = unlimited)
  auto-start-on-full: true

# Queue settings
queue:
  waiting-tip-interval: 5  # How often to show "waiting" tip
  map-voting: true
  maps-to-vote: 3          # How many maps to show in voting
```

### messages.json

All messages are customizable! Example:

```json
{
  "prefix": "§l§bSKYWARS §r§7» §r",
  "queue": {
    "joined": "§aYou joined the {mode} queue!",
    "waiting_for_players": "§eWaiting for players... §7({current}/{min})",
    "game_starting": "§aGame starting in §e{time} §aseconds!"
  }
}
```

**Available Placeholders:**
- `{mode}` - Game mode name
- `{player}` - Player name
- `{time}` - Time remaining
- `{current}` - Current player count
- `{min}` - Minimum players
- `{max}` - Maximum  players
- `{map}` - Arena/map name
- `{kills}` - Kill count
- `{remaining}` - Remaining players
- `{team}` - Team number

## Architecture

### 🏗️ Well-Structured Classes

```
SkyWars/
├── SkyWars.php (Main plugin class)
├── arena/
│   ├── Arena.php (Arena data model)
│   └── ArenaManager.php (Arena CRUD operations)
├── game/
│   ├── GameMode.php (Solo/Duos/Squads enum)
│   ├── GameState.php (Game state enum)
│   └── GameSession.php (Active game instance)
├── queue/
│   ├── QueueLobby.php (Queue lobby management)
│   ├── QueueManager.php (All queues coordinator)
│   └── VoteManager.php (Map voting system)
├── player/
│   ├── PlayerSession.php (Player data tracking)
│   ├── PlayerState.php (Player state enum)
│   └── PlayerManager.php (Session management)
├── manager/
│   └── MessageManager.php (Message system)
├── form/
│   └── FormManager.php (All forms/UI)
├── listener/
│   ├── GameListener.php (In-game events)
│   └── LobbyListener.php (Queue lobby events)
├── command/
│   └── SkyWarsCommand.php (Command handler)
└── utils/
    ├── ItemBuilder.php (Item creation helper)
    └── Countdown.php (Countdown timer)
```

### 🎯 Design Principles

- **Separation of Concerns**: Each class has a single, well-defined responsibility
- **Type Safety**: PHP 8.1+ enums for game modes and states
- **Dependency Injection**: Managers passed via constructor
- **Fluent APIs**: ItemBuilder for readable item creation
- **Event-Driven**: Listeners handle all game events
- **Configurable**: All settings and messages externalized
- **Scalable**: Easy to add new game modes or features

## How It Works

### Queue System
1. `QueueManager` maintains separate `QueueLobby` for each game mode
2. Players join via `/sw join`, selecting their preferred mode
3. `QueueLobby` handles waiting, voting, and countdown
4. When ready, creates `GameSession` and transitions players

### Voting System
1. `VoteManager` initialized with available arenas for the mode
2. Players click vote item → opens form with arena options
3. Votes tracked per player
4. Most voted arena wins, or random if no votes

### Game Session
1. `GameSession` created with arena, mode, and players
2. Teams assigned based on mode (Solo=1, Duos=2, Squads=4)
3. Players teleported to cages
4. 5-second countdown
5. Game starts - death elimination tracked
6. Win condition checked after each elimination
7. Winner announced, all players restored

### Player State Management
1. `PlayerSession` tracks each player's state
2. Original inventory/position saved on join
3. State transitions: LOBBY → QUEUE → IN_GAME → SPECTATING → LOBBY
4. Restored on game end or quit

## Troubleshooting

**"No arenas available"**
- Create at least one arena with `/sw create <name>`
- Ensure arena is valid (minimum 4 cages)
- Check arena supports the selected game mode

**"Waiting for players" never ends**
- Minimum 3 players required
- Check `min-players` in config.yml

**Items not working in queue**
- Ensure messages.json is properly formatted
- Items have NBT tag 'skywars' with value 'vote_item' or 'leave_item'

**Players stuck after game**
- Plugin saves original position before game
- Check console for errors during teleportation

## Support

This plugin is designed to be production-ready with enterprise-level code quality:
- ✅ Full error handling
- ✅ Comprehensive logging
- ✅ Type-safe with PHP 8.1+ features
- ✅ Well-documented code
- ✅ Clean architecture

For issues or questions, review the code - it's extensively commented!

## Credits

**Author**: Marquez  
**API**: PocketMine-MP 5.0.0+  
**License**: MIT

---

**Enjoy your SkyWars games! 🎮**
