## Orbit Mall API

This repository are intended for building Orbit Mall API. But right now this
repository also contains the mobile application. The deployment of this app can
be seen on:

http://mall.earth.dominopos.dev/

Please connect to VPN first to open the above URL.

This document gives you quick guide how to begin working with the project.

* [1. Cloning The Project](#1-cloning-the-project)
* [2. Git Branch](#2-git-branch)
* [3. Environment Setup](#3-environment-setup)
  * [Git Config](#git-config)
  * [VirtualHost Name](#virtualhost-name)
  * [Apache VirtualHost](#apache-virtualhost)
  * [Laravel Config](#laravel-config)
  * [Application Timezone](#application-timezone)
* [4. Coding Style](#4-coding-style)
  * [String Representation](#string-representation)
  * [String Concatenation and Operator](#string-concatenation-and-operator)
  * [Comma Operator](#comma-operator)

### 1. Cloning The Project

The first thing you should do when working with the project of course is cloning the Orbit Shop project. This guide assume you're using command line version of git.

Go to your working directory and issue command below. Assuming the working directory is `/home/rio/project`.

````
$ cd /home/rio/project
$ git clone git@github.com:dominopos/orbit-mall-api.git
````

It should clone the project into `orbit-mall-api` directory. Now you can enter to the directory and begin working with the project.

````
$ cd orbit-mall-api
`````

### 2. Git Branch

Every developer should working on their own branch. Currently there's two main branch which are: **master** and **development**. Every developer should keep up to date with those two branch.

As an example, if there is developer named **john**, then he should create a branch named **john** and working on that branch. Here's the example:

Keep the branch up to date first.

````
$ git fetch origin
````

Make sure you're in *development* branch and merge the latest changes.

````
$ git checkout development
$ git merge origin/development
````

Create your own branch based on *development* branch. As an example _john_ branch here.

````
$ git checkout -b john
````

Only project manager or lead developer the one that should push code changes to the _development_ branch. It will minimize of code conflict and speed up the merging. Every time you want to make changes to your code just make sure you're up to date to the development branch.

Do this on your own branch to keep up to date.

````
$ git fetch origin
$ git merge origin/development
````

The site [http://mall.earth.dominipos.dev](http://mall.earth.dominipos.dev) reflects code that sit in *development* branch.

### 3. Environment Setup

The purpose of environment setup is to standardize settings used on the project.

#### Git Config

Please use your full name not just nick name for git configuration. Provide a valid email address also. Example below will set name and email for this orbit-mall-api repository. Make sure you are in root directory of this project.

````
$ git config user.name "John Doe"
$ git config user.email "john.doe@example.com"
````

Verify your configuration:

````
$ git config user.name
John Doe
$ git config user.email
john.doe@example.com
````

#### VirtualHost Name

Please create new hostname on your Operating System to be used on this project. You should create two new hostname. The first is **orbit-mall.here** and the second one is **orbit-mall.YOUR_NAME**.

As an example if the developer name is **john** then he should create these hostname:

1. orbit-mall.here
2. orbit-mall.rio

#### Apache VirtualHost

This is example of Apache VirtualHost that you can use. Make changes as you need, especially the DocumentRoot and ServerAlias.

````
<VirtualHost *:80>
   ServerAdmin orbit@localhost.org
   DocumentRoot /home/john/project/orbit-mall-api/public
   ServerName  orbit-mall.here
   ServerAlias orbit-mall.john

   <Directory "/home/john/project/orbit-mall-api/public">
        Options Indexes FollowSymLinks
        AllowOverride None

    <IfModule mod_rewrite.c>
        Options +FollowSymLinks
        RewriteEngine On

        # Redirect Trailing Slashes...
        RewriteRule ^(.*)/$ /$1 [L,R=301]

        # Handle Front Controller...
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </IfModule>

    Require ip 127.0.0.0/8 172.16.0.0/16 10.10.0.0/16
    Require all denied
   </Directory>

   ErrorLog ${APACHE_LOG_DIR}/orbit-mall.error.log

   # Possible values include: debug, info, notice, warn, error, crit,
   # alert, emerg.
   LogLevel notice

   CustomLog ${APACHE_LOG_DIR}/orbit-mall.access.log combined
</VirtualHost>
````

#### Laravel Config

For the sake of portability between developers, file `app/config/app.php` and `app/config/database.php` removed from git index tree. These file are replaced with `app/config/app.php.sample` and `app/config/database.php.sample`.

Why we doing this is to prevent changes that made by others developer to the configuration file committed to git index. The configuration between each developer might be different depending on their machine such as database name or database password.

We can do some fancy things inside `bootstrap/start.php` to detect environment, but it has a downside when we want to keep up to date with Laravel upstream repo. The file might be overridden.

So to begin working with project one should copy `app/config/app.php.sample` to `app/config/app.php` and also `app/config/database.php.sample` to `app/config/database.php`. Then make some changes as you need to the configuration file. Here is an example of `app/config/app.php`.

There is also one file specific to Orbit Mall config in `app/config/orbit.php.sample`, you have to copy this file to `app/config/orbit.php`.

#### Application Timezone

Always use UTC when saving data to the database. It is very important so we can easily convert date/time data to any timezone if the source is UTC. Laravel application uses to many magic as example when it saving created_at and updated_at. It is best to keep the application timezone to UTC and display the date and time based on user specific timezone.

It is also important when we doing date calculation the two date should had the same timezone. If not then the result is probably could be wrong.


### 4. Coding Style

Every developer SHOULD follow these rules while writing their code.

1. Follow [PSR-1 Standard](http://www.php-fig.org/psr/psr-1/)
2. Follow [PSR-2 Standard](http://www.php-fig.org/psr/psr-2/)
3. If you need to write comments on your code then write as much as you want as long as it make someone else clear when reading your code.

#### String Representation

When dealing with string in PHP you should use single quote `'` if your string does not contains any variable. You can also use `sprintf` if you want to use single quote but want a dynamic result and this is the preferred way.

````php
$error = 'Your session is expired.'
$message = sprintf('You have %d item(s) in your cart.', $total_item);
````

You should use double quote when your string contains variable and you should wrap it inside brackets `{ }`.

````php
$error = "Sorry, but {$email} does not seems to be valid email address."
````

#### String Concatenation and Operator

You should put spaces between string concatenation operator `.` (the dot). This also applies to operator such as +, -, and such.

Wrong Example:

````php
$message = 'The current time is '.$time.'.';
$grand_total = $discount+$total;
````

Correct Example:

````php
$message = 'The current time is ' . $time . '.';
$grand_total = $discount + $total;
````

#### Comma Operator

Comma operator often used when you write a function arguments or calling a function or method. You should put space after the comma operator.

Wrong Example:

````php
$acl->deny('guest','viewCart');

function foo($bar,$dummy)
{
    // some codes
}
````

Correct Example:

````php
$acl->deny('guest', 'viewCart');

function foo($bar, $dummy)
{
    // some codes
}
````

#### Copyright

(c) Copyright 2014 DominoPOS Pte Ltd.
