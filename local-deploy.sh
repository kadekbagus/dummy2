#!/bin/bash
#
# @author Rio Astamal <rio@dominopos.com>
# @desc Simple shellscript to deploy the content of orbit-mall-api
#       to the server (development).

# Destination server
[ -z "$DEPLOY_SERVER" ] && {
   echo "Missing DEPLOY_SERVER environment variable, e.g: DEPLOY_SERVER=git@hostname."
   exit 1
}

# Directory target
[ -z "$DEPLOY_DIR" ] && {
   echo "Missing TARGET_DIR environment variable, e.g: DEPLOY_DIR=/gotomalls/www/orbit-mall-api/."
   echo "Important: remember to include trailing slash '/' on directory name."
   exit 1
}

rsync -avz --delete \
--exclude=.git/ \
--exclude=app/config/app.php \
--exclude=app/config/database.php \
--exclude=app/config/mail.php \
--exclude=app/config/oauth-4-laravel.php \
--exclude=app/config/orbit-notifier.php \
--exclude=app/config/orbit.php \
--exclude=app/config/mail.php \
--exclude=app/config/queue.php \
--exclude=app/config/packages/laraeval/laraeval/config.php \
--exclude=app/storage/logs/ \
--exclude=app/storage/views/ \
--exclude=app/storage/cache/ \
--exclude=app/storage/meta/ \
--exclude=app/storage/orbit-session/ \
--exclude=app/storage/sessions/ \
--exclude=app/storage/debugbar/ \
--exclude=node_modules/ \
--exclude=public/uploads \
--exclude=public/mobile-ci/scripts/config.js \
--exclude=app/database/elasticsearch-migrations/migrated/ \
--exclude=local-deploy.sh \
./ ${DEPLOY_SERVER}:${DEPLOY_DIR}

# Run npm install on the target server
if [ "$SKIP_NPM" != "yes" ]; then
    echo "Deploy [remote]: Running 'npm install' to install dependencies..."
    ssh $DEPLOY_SERVER "cd '$DEPLOY_DIR' && npm install"
fi

# Run grunt less
if [ "$SKIP_GRUNT" != "yes" ]; then
    echo "Deploy [remote]: Running 'grunt less' on mall api to generate css files..."
    ssh $DEPLOY_SERVER "cd '$DEPLOY_DIR' && grunt less"
fi

# Run migration
if [ "$SKIP_DB_MIGRATION" != "yes" ]; then
    echo "Deploy [remote]: Running 'php artisan migrate --force' to migrate database..."
    ssh $DEPLOY_SERVER "cd '$DEPLOY_DIR' && php artisan migrate --force"
fi
