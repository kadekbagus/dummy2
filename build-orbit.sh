#!/bin/bash
#
echo "-------------------------------------------------------"
echo " Orbit API Base Library Unit Test"
echo "-------------------------------------------------------"
cd vendor/dominopos/orbit-api
composer dump-autoload
phpunit

# Travese back to the original root directory
cd ../../..

[ -f app/config/app.php ] || {
    # File does not exists, let's create it from app.php.sample
    # 1) Get the host name from environment
    # 2) Fallback to 'localhost' if it was empty
    ORBIT_HOST="$( [ ! -z "$ORBIT_HOST" ] && echo "$ORBIT_HOST" || echo "localhost" )"
    sed "s/'url' => 'http:\/\/localhost'/'url' => 'http:\/\/$ORBIT_HOST'/g" < app/config/app.php.sample > app/config/app.php
}

echo "-------------------------------------------------------"
echo " Orbit API Application Unit Test"
echo "-------------------------------------------------------"
echo "Running artisan migrate for production..."
php artisan migrate --force
echo ""

echo "Running artisan migrate for testing..."
php artisan --env=testing migrate
echo ""

echo "-------------------------------------------------------"
echo " Deploying Orbit API Application"
echo "-------------------------------------------------------"
[ ! -z "$ORBIT_DEPLOY_DIR" ] && {
    echo "Running deployment as " $( whoami )
    echo "Deploying application to ${ORBIT_DEPLOY_DIR}..."

    # remove trailing slash
    ORBIT_DEPLOY_DIR="$( echo "$ORBIT_DEPLOY_DIR" | sed "s,/\+$,," )"

    # Change the ownership of the deployment dir to user jenkins
    echo -n "Changing permission on ${ORBIT_DEPLOY_DIR} to user jenkins..."
    $( $ORBIT_CMD_CHOWN_DEPLOY_DIR jenkins )
    echo "done."

    echo -n "Copying files to ${ORBIT_DEPLOY_DIR}..."
    rsync --delete -lrvqW --no-compress --ignore-errors --exclude=.git ./ ${ORBIT_DEPLOY_DIR}/
    echo "done."

    # Change the ownership back to orbitshop:git
    echo -n "Changing permission on ${ORBIT_DEPLOY_DIR} back to orbitshop:git..."
    $( $ORBIT_CMD_CHOWN_DEPLOY_DIR orbitshop )
    echo "done."
}

# Bump version
./bump-version.sh

phpunit -c phpunit-nocolor.xml
