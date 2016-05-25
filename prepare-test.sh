#!/bin/bash
# Prepare environment to run unit testing

ROOT=$( pwd )
TESTDUMMYDIR="${ROOT}/vendor/laracasts/testdummy"
FAKERDIR="${ROOT}/vendor/fzaninotto/faker"

[ -z "$TESTDIR" ] && TESTDIR="${ROOT}/app/config/testing"

if [ -d $TESTDUMMYDIR ]; then
    echo "Directory ${TESTDUMMYDIR} exists skipping install testdummy"
else
    echo "Creating directory for package: 'laracast/testdummy' -- ${TESTDUMMYDIR}"
    mkdir -p "${TESTDUMMYDIR}"
    curl -sSL https://api.github.com/repos/laracasts/TestDummy/tarball/4f1b1830b3b5d6cc03e52a56d8e8d858e4a5da4b \
      | tar zx -C "${TESTDUMMYDIR}" --strip-components 1
fi

if [ -d "${FAKERDIR}" ]; then
    echo "Directory $FAKERDIR exists skipping install faker"
else
    echo "Creating directory for package: 'faker' -- ${TESTDUMMYDIR}"
    mkdir -p "${FAKERDIR}"
    curl -sSL https://api.github.com/repos/fzaninotto/Faker/tarball/010c7efedd88bf31141a02719f51fb44c732d5a0 \
      | tar zx -C "${FAKERDIR}" --strip-components 1
fi

# Database setting
echo Writing "$TESTDIR/database.php"
cat << 'EOF' > "$TESTDIR/database.php"
<?php

return array(
    'fetch' => PDO::FETCH_CLASS,
    'default' => 'mysql',
    'connections' => array(
        'mysql' => array(
            'driver'    => 'mysql',
            'host'      => $_SERVER['ORBIT_DB_HOST'],
            'database'  => $_SERVER['ORBIT_DB_NAME'],
            'username'  => $_SERVER['ORBIT_DB_USERNAME'],
            'password'  => $_SERVER['ORBIT_DB_PASSWORD'],
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => $_SERVER['ORBIT_DB_PREFIX'],
        ),
    ),
    'migrations' => 'migrations',
);
EOF

# App Setting
echo "Writing $TESTDIR/app.php"
cat << 'EOF' > "$TESTDIR/app.php"
<?php

return array(
    'debug' => true,
    'url' => 'http://localhost',
    'timezone' => 'Asia/Jakarta',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'key' => 'TESTINGKEY',
    'cipher' => MCRYPT_RIJNDAEL_128,
    'providers' => array(
        'Illuminate\Foundation\Providers\ArtisanServiceProvider',
        'Illuminate\Auth\AuthServiceProvider',
        'Illuminate\Cache\CacheServiceProvider',
        'Illuminate\Session\CommandsServiceProvider',
        'Illuminate\Foundation\Providers\ConsoleSupportServiceProvider',
        'Illuminate\Routing\ControllerServiceProvider',
        'Illuminate\Cookie\CookieServiceProvider',
        'Illuminate\Database\DatabaseServiceProvider',
        'Illuminate\Encryption\EncryptionServiceProvider',
        'Illuminate\Filesystem\FilesystemServiceProvider',
        'Illuminate\Hashing\HashServiceProvider',
        'Illuminate\Html\HtmlServiceProvider',
        'Illuminate\Log\LogServiceProvider',
        'Illuminate\Mail\MailServiceProvider',
        'Illuminate\Database\MigrationServiceProvider',
        'Illuminate\Pagination\PaginationServiceProvider',
        'Illuminate\Queue\QueueServiceProvider',
        'Illuminate\Redis\RedisServiceProvider',
        'Illuminate\Remote\RemoteServiceProvider',
        'Illuminate\Auth\Reminders\ReminderServiceProvider',
        'Illuminate\Database\SeedServiceProvider',
        'Illuminate\Session\SessionServiceProvider',
        'Illuminate\Translation\TranslationServiceProvider',
        'Illuminate\Validation\ValidationServiceProvider',
        'Illuminate\View\ViewServiceProvider',
        'Illuminate\Workbench\WorkbenchServiceProvider',
        'Laraeval\Laraeval\LaraevalServiceProvider',
    ),
    'manifest' => storage_path().'/meta',
    'aliases' => array(
        'App'               => 'Illuminate\Support\Facades\App',
        'Artisan'           => 'Illuminate\Support\Facades\Artisan',
        'Auth'              => 'Illuminate\Support\Facades\Auth',
        'Blade'             => 'Illuminate\Support\Facades\Blade',
        'Cache'             => 'Illuminate\Support\Facades\Cache',
        'ClassLoader'       => 'Illuminate\Support\ClassLoader',
        'Config'            => 'Illuminate\Support\Facades\Config',
        'Controller'        => 'Illuminate\Routing\Controller',
        'Cookie'            => 'Illuminate\Support\Facades\Cookie',
        'Crypt'             => 'Illuminate\Support\Facades\Crypt',
        'DB'                => 'Illuminate\Support\Facades\DB',
        'Eloquent'          => 'Orbit\Database\ModelWithObjectID',
        'Event'             => 'Illuminate\Support\Facades\Event',
        'File'              => 'Illuminate\Support\Facades\File',
        'Form'              => 'Illuminate\Support\Facades\Form',
        'Hash'              => 'Illuminate\Support\Facades\Hash',
        'HTML'              => 'Illuminate\Support\Facades\HTML',
        'Input'             => 'Illuminate\Support\Facades\Input',
        'Lang'              => 'Illuminate\Support\Facades\Lang',
        'Log'               => 'Illuminate\Support\Facades\Log',
        'Mail'              => 'Illuminate\Support\Facades\Mail',
        'Paginator'         => 'Illuminate\Support\Facades\Paginator',
        'Password'          => 'Illuminate\Support\Facades\Password',
        'Queue'             => 'Illuminate\Support\Facades\Queue',
        'Redirect'          => 'Illuminate\Support\Facades\Redirect',
        'Redis'             => 'Illuminate\Support\Facades\Redis',
        'Request'           => 'Illuminate\Support\Facades\Request',
        'Response'          => 'Illuminate\Support\Facades\Response',
        'Route'             => 'Illuminate\Support\Facades\Route',
        'Schema'            => 'Illuminate\Support\Facades\Schema',
        'Seeder'            => 'Illuminate\Database\Seeder',
        'Session'           => 'Illuminate\Support\Facades\Session',
        'SoftDeletingTrait' => 'Illuminate\Database\Eloquent\SoftDeletingTrait',
        'SSH'               => 'Illuminate\Support\Facades\SSH',
        'Str'               => 'Illuminate\Support\Str',
        'URL'               => 'Illuminate\Support\Facades\URL',
        'Validator'         => 'Illuminate\Support\Facades\Validator',
        'View'              => 'Illuminate\Support\Facades\View',

    ),

);
EOF

# Mail Setting
echo "Writing $TESTDIR/mail.php"
cat << 'EOF' > "$TESTDIR/mail.php"
<?php

return array(
    'driver' => 'log',
);
EOF

# Orbit Setting
cp "$ROOT/app/config/orbit.php.sample" "$TESTDIR/orbit.php"

echo "Prepare test is done."