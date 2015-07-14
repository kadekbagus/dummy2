#!/bin/bash
#
# @author Rio Astamal <me@rioastamal.net>
# @desc Increment the build number of the application.
#

# Path to the php file which storing the build number
export PHP_VERSION_PATH="app/orbit_version.php"
export GIT_SSH=${JENKINS_HOME}/git-orbit-ssh.sh

echo "Going into workspace directory ${WORKSPACE}..."
cd "${WORKSPACE}"

# Store HEAD status
ORBIT_HEAD_COMMIT=$( cat "${WORKSPACE}/.git/HEAD" )
echo "HEAD origin is ${ORBIT_HEAD_COMMIT}"

# Pulling new changes from jenkins branch
echo "Creating branch jenkins..."
git branch jenkins 2>/dev/null

echo "Pulling new changes from jenkins branch..."
git checkout jenkins
ORBIT_SHOP_KEY=${ORBIT_SHOP_DEPLOY_KEY_PATH} git pull origin jenkins

# We are interesting with this line
# --> define('ORBIT_APP_BUILD_NUMBER', XYZ);
# --> define('ORBIT_APP_BUILD_DATE', ABC);
#
# We should replace the 'XYZ' with the BUILD_NUMBER env and ABC with build date.
echo "Bumping build number to ${BUILD_NUMBER}..."
sed -i "s/\(ORBIT_APP_BUILD_NUMBER\x27,\)\s\([0-9]\+\)/\1 $BUILD_NUMBER/" ${PHP_VERSION_PATH}

echo "Bumping build date to ${BUILD_ID}..."
sed -i "s/\(ORBIT_APP_BUILD_DATE\x27,\)\s\(\x27*\x27\));/\1 \x27$BUILD_ID\x27);/" ${PHP_VERSION_PATH}

# Commit to the origin repository
echo "Committing the changes for build number ${BUILD_NUMBER}..."
git add ${PHP_VERSION_PATH} && git commit -m "Jenkins: Bump build number to ${BUILD_NUMBER}"

echo "Pushing new build number ${BUILD_NUMBER} to github..."
ORBIT_SHOP_KEY=${ORBIT_SHOP_DEPLOY_KEY_PATH} git push origin jenkins:jenkins

# checking out back to the development branch
git checkout ${ORBIT_HEAD_COMMIT}
