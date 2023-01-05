# Laravel LiteFS
You can run multiple instances of your Laravel Fly App with in-sync SQLite data with the help of LiteFS!

<b>This repository provides a reference for the minimal requirements to get started with integrating LiteFS:</b>
1. SQLite and LiteFS configuration in `Dockerfile` | `fly.toml` | `.fly/entrypoint.sh` and the `etc` folder
2. Middleware to forward write requests to the proper instance in `app/Http/Middleware/FlyReplayLiteFSWrite.php`


<b>And additional bonuses for the complete experience:</b>
1. A sample model, controller, view, and route for creating and listing Post entries 
2. Scripts and configuration for allocating a volume to your Laravel storage folder in: `fly.toml` and `.fly/scripts/1_storage_init.sh`

