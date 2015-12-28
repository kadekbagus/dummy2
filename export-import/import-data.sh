#!/bin/bash
#
# @author Rio Astamal <me@rioastamal.net>
# @desc Script to import Orbit database and its resources from a single archive
#       file.
#

MYPID=$$
SOURCE_FILE=
MYSQLPATH=
MYSQLDB=
MYSQLUSER=
MYSQLPASSWORD=
ORBIT_BASE_DIR=
ORBIT_UPLOAD_DIR=
UPLOAD_OWNER=
FORCE_IMPORT="no"

function help() {
    echo "Usage: $0 [OPTIONS]..."
    echo ""
    echo "OPTIONS:"
    echo " -s FILE               Import the data from an archive file named FILE."
    echo " -m PATH               PATH to the mysql binary file."
    echo " -d DATABASE           Set the name of target database to DATABASE."
    echo " -u USERNAME           Set username of the MySQL to USERNAME."
    echo " -p PASSWORD           Set password of the MySQL to PASSWORD."
    echo " -l BASEPATH           Location of the orbit base directory set to BASEPATH."
    echo " -w OWNER              Owner of public/uploads/ directory set to OWNER."
    echo " -f                    Force the import even the version is different."
    echo " -h                    Print this screen and exit."
    echo ""
    echo "Example: "
    echo " $0 \\
 -s /var/backup/orbit-dump-0.12.20150404T103700.tar.gz \\
 -m /usr/bin/mysql -d orbit_shop -u abc -p 123 \\
 -l /var/www/production/orbit-shop -w www-data"
    exit 1
}

function invalidarg() {
    local MSG="${1}"

    echo -e "Error: ${MSG}\n" && help
}

# Check number of arguments
if [ $# -eq 0 ]; then
    help
fi

while getopts s:m:d:u:p:l:w:hf ARG;
do
    case "${ARG}" in
        s)
            # The source archive file
            [ -f "${OPTARG}" ] || {
                echo "Could not find or open source file '${OPTARG}'."
                exit 2;
            }

            SOURCE_FILE="${OPTARG}"
        ;;

        m)
            # Check the existence of mysqldump binary file
            [ -f "${OPTARG}" ] || {
                echo "Could not find the path of mysql binary: ${OPTARG}."
                exit 2
            }

            MYSQLPATH="${OPTARG}"
        ;;

        d)
            MYSQLDB="${OPTARG}"
        ;;

        u)
            # MySQL username
            MYSQLUSER="${OPTARG}"
        ;;

        p)
            # MySQL Password
            MYSQLPASSWORD="${OPTARG}"
        ;;

        l)
            # Orbit uploads dir
            ORBIT_BASE_DIR=$( readlink -f ${OPTARG} )

            # Check for file app/orbit_version.php to determine whether we are
            # on the right directory.
            [ -f "${ORBIT_BASE_DIR}/app/orbit_version.php" ] || {
                echo "It seems your orbit base directory is invalid."
                exit 3
            }

            ORBIT_UPLOAD_DIR="${ORBIT_BASE_DIR}/public"
        ;;

        w)
            # Owner of the uploads dir
            UPLOAD_OWNER="${OPTARG}"
        ;;

        f)
            # Force import
            FORCE_IMPORT="yes"
        ;;

        *)
            help
        ;;
    esac
done

# Check each of arguments before starting to do the import process.

# Check source file
[ -z "${SOURCE_FILE}" ] && invalidarg "You need to specify source file using -s."

# Check MySQL binary path
[ -z "${MYSQLPATH}" ] && invalidarg "You need to specify mysql binary path using -m."

# Check MySQL database name
[ -z "${MYSQLDB}" ] && invalidarg "You need to specify mysql database name using -d."

# Check MySQL user
[ -z "${MYSQLUSER}" ] && invalidarg "You need to specify mysql user using -u."

# Check MySQL password
[ -z "${MYSQLPASSWORD}" ] && invalidarg "You need to specify mysql password using -p."

# Check Orbit base directory
[ -z "${ORBIT_BASE_DIR}" ] && invalidarg "You need to specify orbit base directory using -l."

# Check the owner of upload directory
[ -z "${UPLOAD_OWNER}" ] && invalidarg "You need to specify the owner of public/uploads/ using -w."

# Prepare the temporary directory for extracting the archive
echo -n "Preparing temporary directory..."

# Remove the .tar.gz from source file name
ARCHIVE_DIR="$( echo $( basename ${SOURCE_FILE} ) | sed 's/\.tar\.gz//' )"

ORBIT_TMP_DIR=${ORBIT_BASE_DIR}/export-import/data/import/processing/${ARCHIVE_DIR}

mkdir -p "$ORBIT_TMP_DIR" 2>/dev/null || {
    echo "FAILED."
    exit 5;
}
echo "done."

# Extract the content of archive file
echo -n "Extracting the content of ${SOURCE_FILE} into orbit temp dir..."
tar -zxf "${SOURCE_FILE}" -C "${ORBIT_TMP_DIR}" || {
    echo "FAILED extracting ${SOURCE_FILE}."
}
echo "extract done."

# Get the version of current orbit
# The #) at the end are just to make syntax highlighting behaves correctly
CURRENT_ORBIT_VERSION=$( grep "define('ORBIT_APP_VERSION" ${ORBIT_BASE_DIR}/app/orbit_version.php | sed "s/define('ORBIT_APP_VERSION', '\(.*\)');/\1/" ) #)

# Trim the whitespace
CURRENT_ORBIT_VERSION=$( echo ${CURRENT_ORBIT_VERSION} )

# Get orbit version of the source file
SOURCE_ORBIT_VERSION=$( cat ${ORBIT_TMP_DIR}/orbit-version.txt )

[ "${FORCE_IMPORT}" != "yes" ] && {
    [ "${CURRENT_ORBIT_VERSION}" = "${SOURCE_ORBIT_VERSION}" ] || {
        echo "Could not import since orbit version is different."
        echo "Current version: ${CURRENT_ORBIT_VERSION}."
        echo "Source version: ${SOURCE_ORBIT_VERSION}."
        echo ""
        echo "Use -f to force the import process, but it might introduce some compatibility issue."
        exit 6
    }
}

# Drop all tables
echo -n "Dropping all current MySQL tables..."
mysql -u ${MYSQLUSER} -p${MYSQLPASSWORD} -Nse 'show tables' --database ${MYSQLDB} | \
while read table;
do
    mysql -u ${MYSQLUSER} -p${MYSQLPASSWORD} -e "drop table ${table}" --database ${MYSQLDB}
done
echo "done."

# Import all tables
echo -n "Importing all MySQL tables..."
mysql -u ${MYSQLUSER} -p${MYSQLPASSWORD} --database ${MYSQLDB} < ${ORBIT_TMP_DIR}/orbit-${SOURCE_ORBIT_VERSION}-struct.sql
mysql -u ${MYSQLUSER} -p${MYSQLPASSWORD} --database ${MYSQLDB} < ${ORBIT_TMP_DIR}/orbit-${SOURCE_ORBIT_VERSION}-data.sql
echo "done."

# Import all pictures
echo -n "Importing all upload pictures..."
cp -a ${ORBIT_TMP_DIR}/uploads ${ORBIT_UPLOAD_DIR}
echo "done."

# Cleaning up the temporary directory
# echo -n "Cleaning up temporary directory..."
# rm -rf ${ORBIT_TMP_DIR}
# echo "done."

echo "Changing permission of uploads directory to ${UPLOAD_OWNER}..."
chown -R ${UPLOAD_OWNER}:${UPLOAD_OWNER} ${ORBIT_UPLOAD_DIR}/uploads 2>/dev/null || {
    echo "WARNING: Failed changing permission."
}
echo "done."
