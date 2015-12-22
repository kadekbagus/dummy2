#!/bin/bash
#
# @author Rio Astamal <me@rioastamal.net>
# @desc Script to export Orbit database and its resources to a single archive
#       file.
#

MYPID=$$
TARGET_DIR=
MYSQLDUMP_PATH=
MYSQLDB=
MYSQLUSER=
MYSQLPASSWORD=
MYSQLPREFIX='orb_'
IGNORETABLES=
ORBIT_BASE_DIR=
ORBIT_UPLOAD_DIR=

function help() {
    echo "Usage: $0 [OPTIONS]..."
    echo ""
    echo "OPTIONS:"
    echo " -o DIR                Output the result to DIR."
    echo " -m PATH               PATH to the mysqldump binary file."
    echo " -d DATABASE           Set the name of target database to DATABASE."
    echo " -u USERNAME           Set username of the MySQL to USERNAME."
    echo " -p PASSWORD           Set password of the MySQL to PASSWORD."
    echo " -e PREFIX             Set the table prefix of table name to PREFIX."
    echo " -k TABLE_1[,TABLE_N]  Skip the table named TABLE_1 and TABLE_N."
    echo " -l BASEPATH           Location of the orbit base directory set to BASEPATH."
    echo " -h                    Print this screen and exit."
    echo ""
    echo "Example: "
    echo " $0 -o /var/backup -m /usr/bin/mysqldump \\
 -d orbit_shop -u abc -p 123 \\
 -l /var/www/production/orbit-shop"
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

while getopts o:m:d:u:p:l:e:k:h ARG;
do
    case "${ARG}" in
        o)
            # Check if the dir is writeable
            TARGET_DIR=$OPTARG
            mkdir -p "${TARGET_DIR}" 2>/dev/null || {
                echo "Could not make directory ${TARGET_DIR}."
                exit 1
            }
        ;;

        m)
            # Check the existence of mysqldump binary file
            [ -f "${OPTARG}" ] || {
                echo "Could not find the path of mysqldump: ${OPTARG}."
                exit 2
            }

            MYSQLDUMP_PATH="${OPTARG}"
        ;;

        d)
            MYSQLDB=${OPTARG}
        ;;

        u)
            # MySQL username
            MYSQLUSER="${OPTARG}"
        ;;

        p)
            # MySQL Password
            MYSQLPASSWORD="${OPTARG}"
        ;;

        e)
            # MySQL Prefix table
            [ ! -z "${OPTARG}" ] && {
                MYSQLPREFIX=${OPTARG}
            }
        ;;

        k)
            # Skip MySQL Table
            [ ! -z "${OPTARG}" ] && {
                # Split the comma
                SKIP_TABLES=(${OPTARG//,/ })

                IGNORETABLES=""
                for TABLE in ${SKIP_TABLES[@]}
                do
                    IGNORETABLES=${IGNORETABLES}" --ignore-table=${MYSQLDB}.${MYSQLPREFIX}${TABLE}"
                done
            }
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

        *)
            help
        ;;
    esac
done

# Check each of arguments before starting to do the import process.

# Check source file
[ -z "${TARGET_DIR}" ] && invalidarg "You need to specify source output directory using -o."

# Check mysqldump binary path
[ -z "${MYSQLDUMP_PATH}" ] && invalidarg "You need to specify mysqldump binary path using -m."

# Check MySQL database name
[ -z "${MYSQLDB}" ] && invalidarg "You need to specify mysql database name using -d."

# Check MySQL user
[ -z "${MYSQLUSER}" ] && invalidarg "You need to specify mysql user using -u."

# Check MySQL password
[ -z "${MYSQLPASSWORD}" ] && invalidarg "You need to specify mysql password using -p."

# Check Orbit base directory
[ -z "${ORBIT_BASE_DIR}" ] && invalidarg "You need to specify orbit base directory using -l."

# Function to to dump orbit mysql data
function orbitdump_sql()
{
    local TARGET=$1
    local MYSQLDUMP=$2
    local MDB=$3
    local MUSER=$4
    local MPASS=$5
    local MPREFIX=$6

    # Run mysqldump to export the database structure
    echo -n "Dumping the structure of orbit database..."
    ${MYSQLDUMP} -u ${MUSER} -p${MPASS} ${MDB} --no-data > ${TARGET}-struct.sql || {
        echo "Failed dumping the database structure.";
        exit 4;
    }
    echo "done."

    # Run mysqldump to export the data excluding user activities
    echo -n "Dumping orbit database data..."
    ${MYSQLDUMP} -u ${MUSER} -p${MPASS} --no-create-info ${IGNORETABLES} ${MDB} > ${TARGET}-data.sql || {
        echo "Failed dumping the database data.";
        exit 4;

    }
    echo "done."
}

# Get the version of current orbit
# The #) at the end are just to make syntax highlighting behaves correctly
ORBIT_VERSION=$( grep "define('ORBIT_APP_VERSION" ${ORBIT_BASE_DIR}/app/orbit_version.php | sed "s/define('ORBIT_APP_VERSION', '\(.*\)');/\1/" ) #)

# Trim the whitespace
ORBIT_VERSION=$( echo ${ORBIT_VERSION} )

# Full path to the target directory
TARGET_DIR_FULLPATH=$( readlink -f ${TARGET_DIR} )

# Archive name
ARCHIVE_NAME=orbit-dump-${ORBIT_VERSION}.$( date +%Y%m%dT%H%M%S )

# Create a directory named $TARGET_DIR/export/processing.$MYPID
# So we know that the export proces is not done yet.
PROCESSING_DIR_NAME=${TARGET_DIR_FULLPATH}/export/processing/${ARCHIVE_NAME}.${MYPID}
DONE_DIR_NAME="${TARGET_DIR_FULLPATH}/export/done/"

mkdir -p ${PROCESSING_DIR_NAME} 2>/dev/null || {
    echo "Failed creating 'processing' dirname: ${PROCESSING_DIR_NAME}";
    exit 5;
}
mkdir -p ${DONE_DIR_NAME} 2>/dev/null || {
    echo "Failed creating 'done' dirname:  ${DONE_DIR_NAME}"
}

orbitdump_sql ${PROCESSING_DIR_NAME}/orbit-${ORBIT_VERSION} \
              "${MYSQLDUMP_PATH}" "${MYSQLDB}" "${MYSQLUSER}" \
              "${MYSQLPASSWORD}" "${MYSQLPREFIX}"

echo -n "Copying uploads directory..."
cp -R ${ORBIT_UPLOAD_DIR}/uploads ${PROCESSING_DIR_NAME}/ || {
    echo "Failed."
    exit 6;
}
echo "done."

echo ${ORBIT_VERSION} > ${PROCESSING_DIR_NAME}/orbit-version.txt

# Create tar archive
echo -n "Creating archive for export..."
cd ${PROCESSING_DIR_NAME} && \
tar -zcf ${ARCHIVE_NAME}.tar.gz \
    orbit-version.txt orbit-${ORBIT_VERSION}-struct.sql \
    orbit-${ORBIT_VERSION}-data.sql uploads
echo "done."

# If we goes here then the process is done. We need to move the archive to the
# done directory.
mv ${PROCESSING_DIR_NAME}/${ARCHIVE_NAME}.tar.gz ${DONE_DIR_NAME}/${ARCHIVE_NAME}.tar.gz
# rm -rf ${PROCESSING_DIR_NAME}

echo "Export is done."
exit 0
