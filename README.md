# Multi-instance Laravel-SQLite with LiteFS
You can run multiple instances of your [Laravel Fly Application](https://fly.io/docs/laravel/) with SQLite as primary database. You'll just have to make sure that each node's SQLite database is in-sync with each other. This is easily achievable by configuring your Laravel Fly App with [LiteFS](https://fly.io/docs/litefs/)! 

[`LiteFS`](https://fly.io/docs/litefs/how-it-works/) is a distributed file system that enables you to sync SQLite data across instances of your application.

This repository is a sample Laravel Fly-configured, LiteFS-configured application for reference.

## Requirements
1) Make sure you have a [Laravel Fly App](https://fly.io/docs/laravel/) deployed and running
2) Make sure you have [more than one](https://fly.io/docs/flyctl/scale-count/#usage) instance of your Laravel Fly App
3) Allocate [volumes](https://fly.io/docs/reference/volumes/) (with the same name) for each region you'll be deploying your instances in

## Repository Overview
<b>This repository provides a reference for the minimal requirements to get started with integrating LiteFS:</b>
1. Fly.io, Volumed-SQLite, and LiteFS configuration in [`Dockerfile`](https://github.com/fly-apps/fly-laravel-litefs/blob/main/Dockerfile) | [`fly.toml`](https://github.com/fly-apps/fly-laravel-litefs/blob/main/fly.toml) | [`.fly/entrypoint.sh`](https://github.com/fly-apps/fly-laravel-litefs/blob/main/.fly/entrypoint.sh) and the [`etc`](https://github.com/fly-apps/fly-laravel-litefs/tree/main/etc) folder
2. Middleware to forward write requests to the proper instance in [`app/Http/Middleware/FlyReplayLiteFSWrite.php`](https://github.com/fly-apps/fly-laravel-litefs/blob/main/app/Http/Middleware/FlyReplayLiteFSWrite.php)

<b>Along with additional bonuses for the complete experience:</b>
1. A sample [model](https://github.com/fly-apps/fly-laravel-litefs/blob/main/app/Models/Post.php), [controller](https://github.com/fly-apps/fly-laravel-litefs/blob/main/app/Http/Controllers/PostController.php), [view](https://github.com/fly-apps/fly-laravel-litefs/blob/main/resources/views/posts/create.blade.php), and route for creating and listing Post entries 
2. Scripts and configuration for allocating a volume to your Laravel storage folder in: [`fly.toml`](https://github.com/fly-apps/fly-laravel-litefs/blob/main/fly.toml) and [`.fly/scripts/1_storage_init.sh`](https://github.com/fly-apps/fly-laravel-litefs/blob/main/.fly/scripts/1_storage_init.sh)



# LiteFS Configuration

Once you have a running Laravel Fly application, certain changes need to be done to the Fly.io generated files:

### [fly.toml](https://github.com/fly-apps/fly-laravel-litefs/blob/main/fly.toml)

Set your SQLite connection in the storage directory. 
```
[env]
    ...
    DB_CONNECTION="sqlite"
    DB_DATABASE="/var/www/html/storage/database/database.sqlite"
```
If you prefer to use consul for leader election, go ahead and enable it under [experimental]:
```
[experimental]
  ...
  enable_consul = true
```
To persist data in your `storage` folder, make sure to mount volume to the directory:
```
[mounts]
  source="storage_vol"
  destination="/var/www/html/storage"
```

### [.fly/scripts/1_storage_init.sh](https://github.com/fly-apps/fly-laravel-litefs/blob/main/.fly/scripts/1_storage_init.sh) 
Mounting a Volume to a folder will initially erase any item it contains during the first time the Volume is mounted for the folder. In order to fix the volumized-storage-content-erasure issue above, one quick way is to re-initialize its content from a back up folder with a [`startup script`](https://fly.io/docs/laravel/the-basics/customizing-deployments/#startup-scripts):

```
FOLDER=/var/www/html/storage/app
if [ ! -d "$FOLDER" ]; then
    echo "$FOLDER is not a directory, copying storage_ content to storage"
    cp -r /var/www/html/storage_/. /var/www/html/storage
    echo "Deleting storage_..."
    rm -rf /var/www/html/storage_
fi
```

### [Dockerfile](https://github.com/fly-apps/fly-laravel-litefs/blob/main/Dockerfile)

In your dockerfile you'll need to retrieve the LiteFS image, install packages required in using LiteFS, and copy litefs configuration files to the [proper location](https://fly.io/docs/litefs/config/#config-file-search-path) in your running container:
```
# Get LiteFS image
FROM flyio/litefs:pr-251 AS litefs

# Install LITEFS dependencies
RUN apt-get update && apt-get install bash fuse 

# COPY locally created LITEFS config files from etc to the a proper location LiteFS can access
COPY --from=litefs /usr/local/bin/litefs /usr/local/bin/litefs
ADD etc/litefs.yml /etc/litefs.yml
ADD etc/fuse.conf /etc/fuse.conf
```

### [.fly/entrypoint.sh](https://github.com/fly-apps/fly-laravel-litefs/blob/main/.fly/entrypoint.sh) 
This is our ENTRYPOINT in our Dockerfile, and the script that runs startup scripts and afterwards our server. We'll need to revise this file to ensure we run LiteFS instead of starting our server--once LiteFS successfully runs, it should also handle starting our server:
```
exec litefs mount
```

---

Lastly, additional LiteFS-specific configuration files should be created to successfully run LiteFS in a Laravel web application:

### [etc/litefs.yml](https://github.com/fly-apps/fly-laravel-litefs/blob/main/etc/litefs.yml)
This file serves as the configuration reference LiteFS will use on `litefs mount` above. You can find a whole reference on it [here](https://fly.io/docs/litefs/config/#config-file-search-path). This section highlights the Laravel-config relevant parts of the [file](https://github.com/fly-apps/fly-laravel-litefs/blob/main/etc/litefs.yml) we've created for this repository.


```
fuse:
    dir: "/var/www/html/storage/database"
    allow-other: true

exec: "supervisord -c /etc/supervisor/supervisord.conf"
```

<b>1. The fuse.dir</b> - Your Laravel application will make database transaction requests to the specified connection in your DB_DATABASE env. LiteFS needs to know where these transactions are happening, and so we specify the directory of our DB_DATABASE value in `fuse.dir`.

<b>2. The fuse.allow-other</b> - Once LiteFS has mounted the fuse in the fuse.dir, that directory's ownership gets updated to root. In order to allow none-root users to access the database.sqlite file found in that directory, include a `fuse.allow-other:true` option.

<b>3. The exec</b> - Finally, we arrive at the `exec` block. We can use this subprocess to start our Laravel server. 

### [etc/fuse.conf](https://github.com/fly-apps/fly-laravel-litefs/blob/main/etc/fuse.conf)

The etc/fuse.conf file is required to enable the etc/litefs.yml's fuse.allow-other option to work. It only contains one line:

```
user_allow_other
```

# Forwarding Write Requests with [fly-replay](https://fly.io/docs/reference/fly-replay/#fly-replay) [Middleware](https://github.com/fly-apps/fly-laravel-litefs/blob/main/app/Http/Middleware/FlyReplayLiteFSWrite.php)

[LiteFS](https://fly.io/docs/litefs/how-it-works/#cluster-management-using-leases) works by restricting database writes to one, primary node. Read requests are available to all instances, but only the primary node instance can make write requests and propagate changes to the rest of the cluster( "replicas" ):

## Fly-replay Middleware
Write requests received by a "replica" instance of your application needs to be forwarded to the primary node for successful writes. We can easily forward requests to the correct primary node instance with the use of [fly-replay](https://fly.io/docs/reference/fly-replay/#fly-replay) response header with a [middleware](https://github.com/fly-apps/fly-laravel-litefs/blob/main/app/Http/Middleware/FlyReplayLiteFSWrite.php). 

#### [app/Http/Middleware/FlyReplayLiteFSWrite.php](https://github.com/fly-apps/fly-laravel-litefs/blob/main/app/Http/Middleware/FlyReplayLiteFSWrite.php)

```
return response('', 200, [
    'fly-replay' => "instance=$primaryNodeId",
]);
```

LiteFS includes a `.primary file` pointing to the instance id of our "primary node" instance. Therefore, we can simply create a middleware to get the [primary node](https://fly.io/docs/litefs/primary/) and once available, forward the request to that primary instance by returning a response with the `fly-replay` response header containing the instance id. 

## Fly-replay and CSRF Tokens

Lastly, for [`fly-replay`](https://fly.io/docs/reference/fly-replay/#fly-replay) forwarded requests to work, make sure to update your SESSION_DRIVER to a <b>none-file driver</b>.

[Laravel's generated CSRF tokens](https://laravel.com/docs/9.x/csrf#:~:text=Laravel%20automatically%20generates%20a%20CSRF,the%20requests%20to%20the%20application.) created in an instance won't be available in other instances of your application. This means you'll get a token error if your application's CSRF tokens are saved in your instance's file storage with a file session driver.

The [simplest solution](https://fly.io/laravel-bytes/taking-laravel-global/#problem-session-storage) would be to use a secured cookie-based session configuration. Update your fly.toml with the following env:
```
[env]
  ...
  SESSION_DRIVER=cookie
  SESSION_SECURE_COOKIE=true
```

## Applying the Fly-replay Middleware to Post Routes
The middleware created above is intended to be a [route middleware](https://github.com/fly-apps/fly-laravel-litefs/blob/main/app/Http/Kernel.php):
```
protected $routeMiddleware = [
  ...
  'fly-replay.litefs-write' => \App\Http\Middleware\FlyReplayLiteFSWrite::class
]
```

That should be applied to post routes:
```
Route::post('/posts/store',[\App\Http\Controllers\PostController::class,'store'])->middleware(['fly-replay.litefs-write']);;
```