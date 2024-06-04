# Multi-instance Laravel-SQLite with LiteFS
You can run multiple instances of your [Laravel Fly Application](https://fly.io/docs/laravel/) with SQLite as primary database. You'll just have to make sure that each node's SQLite database is in-sync with each other. This is easily achievable by configuring your Laravel Fly App with [LiteFS](https://fly.io/docs/litefs/)! 

[`LiteFS`](https://fly.io/docs/litefs/how-it-works/) is a distributed file system that enables you to sync SQLite data across instances of your application.

This repository is a sample Laravel Fly-configured, LiteFS-configured application for reference.

## Requirements
1) Make sure you have a [Laravel Fly App](https://fly.io/docs/laravel/) deployed and running
2) Make sure you have [more than one](https://fly.io/docs/flyctl/scale-count/#usage) instance of your Laravel Fly App
3) Allocate [volumes](https://fly.io/docs/reference/volumes/) (with the same name) for each region you'll be deploying your instances in
4) You've attached consul to the Laravel Fly App by running `fly consul attach`

## Repository Overview
<b>This repository provides a reference for the minimal requirements to get started with integrating LiteFS:</b>
1. Fly.io, Volumed-SQLite, and LiteFS configuration in [`Dockerfile`](https://github.com/fly-apps/fly-laravel-litefs/blob/main/Dockerfile) | [`fly.toml`](https://github.com/fly-apps/fly-laravel-litefs/blob/main/fly.toml) | [`.fly/entrypoint.sh`](https://github.com/fly-apps/fly-laravel-litefs/blob/main/.fly/entrypoint.sh) and the [`etc`](https://github.com/fly-apps/fly-laravel-litefs/tree/main/etc) folder


<b>Along with additional bonuses for the complete experience:</b>
1. A sample [model](https://github.com/fly-apps/fly-laravel-litefs/blob/main/app/Models/Post.php), [controller](https://github.com/fly-apps/fly-laravel-litefs/blob/main/app/Http/Controllers/PostController.php), [view](https://github.com/fly-apps/fly-laravel-litefs/blob/main/resources/views/posts/create.blade.php), and route for creating and listing Post entries 


# LiteFS Configuration

Once you have a running Laravel Fly application, certain changes need to be done to your project's files:

### SubFolders
First create a subfolder `database` inside the `storage` folder. We'll later mount a volume to this sub-directory to persist data on it and all its sub-directories. It will contain sub-directories: "`database/app`" to hold the sqlite file used by our Laravel app, and "`database/litefs`" for litefs to use in storing its files:
```
# We mount a volume to this later
# to persist data it contains
mkdir storage/database

# To hold our sqlite file
mkdir storage/database/app

# For litefs' to store its files
mkdir storage/database/litefs
```


### [fly.toml](https://github.com/fly-apps/fly-laravel-litefs/blob/main/fly.toml)

Set the SQLite connection in the `storage/database/app` directory to use a `database.sqlite` file:
```
[env]
    ...
    DB_CONNECTION="sqlite"
    DB_DATABASE="/var/www/html/storage/database/app/database.sqlite"
```

Then, also mount a volume on this `/var/www/html/storage/database` directory to persist data on it:
```
[[mounts]]
  source = 'litefs'
  destination = '/var/www/html/storage/database'
```

Once you complete revising the `fly.toml` file, proceed with creating LiteFS-specific configuration files needed to successfully run LiteFS in a Laravel web application:

### [etc/litefs.yml](https://github.com/fly-apps/fly-laravel-litefs/blob/main/etc/litefs.yml)
This file serves as the configuration reference LiteFS will use on `litefs mount` above. You can find a whole reference on it [here](https://fly.io/docs/litefs/config/#config-file-search-path). This section highlights the Laravel-config relevant parts of the [file](https://github.com/fly-apps/fly-laravel-litefs/blob/main/etc/litefs.yml) we've created for this repository.


```yml
fuse:
    # This is the folder our database.sqlite is located in, as we've specified in our fly.toml file's env.DB_DATABASE attribute 
    dir: "/var/www/html/storage/database/app"
    allow-other: true

data:
  # This is the folder that litefs will use
  dir: "/var/www/html/storage/database/litefs"

exec: 
  - cmd: "php /var/www/html artisan migrate --force"
  - cmd: "supervisord -c /etc/supervisor/supervisord.conf"

```

<b>1. The fuse.dir</b> - Your Laravel application will make database transaction requests to the specified connection in your DB_DATABASE env. LiteFS needs to know where these transactions are happening, and so we specify the directory of our DB_DATABASE value in `fuse.dir`.

<b>2. The fuse.allow-other</b> - Once LiteFS has mounted the fuse in the fuse.dir, that directory's ownership gets updated to root. In order to allow none-root users to access the database.sqlite file found in that directory, include a `fuse.allow-other:true` option.

<b>4. The data.dir</b> - This is the directory LiteFS will use for its transactions. 

<b>5. The exec</b> - Finally, we arrive at the `exec` block. We can use this subprocess to run our migrations, and most importantly start our server! 

# Forwarding Write Requests to the Primary Node
We want to forward write transactions to the primary node and not replica nodes. We can use LiteFS's built in proxy layer for forwarding write requests to the primary node.

We can do this by adding the proxy section in our etc/litefs.yml file:

```yml
# etc/litefs.yml

proxy:
  # This is the port the proxy will use
  addr: ":8081"
  # This is where our Laravel app will serve, should match the internal_port with the http_service specified in fly.toml
  target: "localhost:8080"
  # This is the sqlite file it will handle transactions for, so make sure it's the same file used by the app
  db: "database.sqlite"
  passthrough: 
    - "*.ico"
    - "*.png"
```
**NOTES:**
1. Please make sure the target port matches with the internal_port we've specified in the internal_port of the http_service specified in fly.toml. 

2. For the db value, please make sure to provide the full name of the sqlite database used by our Laravel app. Which we've specified in our fly.toml file.


### [etc/fuse.conf](https://github.com/fly-apps/fly-laravel-litefs/blob/main/etc/fuse.conf)

The etc/fuse.conf file is required to enable the etc/litefs.yml's fuse.allow-other option to work. It only contains one line:

```
user_allow_other
```

Finally, we revise files generated for our app by Fly.io during `fly launch` to use LiteFS with our app:

### [Dockerfile](https://github.com/fly-apps/fly-laravel-litefs/blob/main/Dockerfile)

In your dockerfile you'll need to retrieve the LiteFS image, install packages required by LiteFS, and copy litefs configuration files to the [proper location](https://fly.io/docs/litefs/config/#config-file-search-path) in your running container:
```
# LITEFS Dependencies
RUN apt-get update -y && apt-get install -y ca-certificates fuse3 sqlite3

# LITEFS Binary
COPY --from=flyio/litefs:0.5 /usr/local/bin/litefs /usr/local/bin/litefs

# LITEFS config file move to proper location at /etc
COPY etc/litefs.yml /etc/litefs.yml
COPY etc/fuse.conf /etc/fuse.conf
```

### [.fly/entrypoint.sh](https://github.com/fly-apps/fly-laravel-litefs/blob/main/.fly/entrypoint.sh) 
This is our ENTRYPOINT in our Dockerfile, and the script that runs startup scripts and afterwards our server. We'll need to revise this file to ensure we run LiteFS instead of starting our server--once LiteFS successfully runs, it should also handle starting our server:
```
exec litefs mount
```

---


### ERRORS!
If you encounter errors in your logs regarding litefs, you can read this page for steps you can follow to possibly fix the error.
Some common errors are:

1. "cannot become primary, local node has no cluster ID and \"consul\" lease already initialized with cluster ID LFSC0C41533E99503312"
- to fix this, you'll have to assign a new cluster id your machines. This can be done two ways, with the easiest way being an update of the string value specified in your litefs.yml file's `lease.consul.key` value to a unique value you've not yet specified for your current app. 

```
# i.e

# Update your existing key from an old value:
lease
  consul:
    key: "litefs/${FLY_APP_NAME}"

---------------------------------------------------------

# TO something different:
lease:
  consul:
    key: "litefs/${FLY_APP_NAME}-2" 
```


