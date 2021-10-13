The plugin you need to keep your server lagg free! 

| Phar | View Count |
| :---: | :---: |
 [![Download](https://img.shields.io/badge/download-latest-blue.svg)](https://poggit.pmmp.io/ci/TwistedAsylumMC/AutoClearLagg/~) | [![View Count](http://hits.dwyl.io/TwistedAsylumMC/AutoClearLagg.svg)](http://hits.dwyl.io/TwistedAsylumMC/AutoClearLagg) |

# Features
- [x] Enable/Disable Mobs & Items clearing
- [x] NEW: Exempt entities from clearing
- [x] Configurable Timer
- [x] Editabe Messages

# config.yml
```---
# AutoClearLagg (ACL) Config

# Seconds between each clear lagg
seconds: 300

# Entities to clear
clear:
  items: true
  mobs: true
  exempt: # Array of entities not to clear
    - Zombie
    - Pig

messages:
  time-left: "§cEntities will clear in {SECONDS} seconds"
  entities-cleared: "§cCleared a total of {COUNT} entities"

# Countdown times
times: [60, 30, 15, 10, 5, 4, 3, 2, 1]
...```

