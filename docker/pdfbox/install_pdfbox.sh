
#/bin/bash

#### <Safety Net>
set -o errexit # Exit immediately if a pipeline returns non-zero.
trap 'echo "Aborting due to errexit on line $LINENO. Exit code: $?" >&2' ERR # Print a helpful message if that happens
set -o errtrace # Allow the above trap be inherited by all functions in the script.
set -o pipefail # Return code of a pipeline is the right-most failure. 0 if none.
#### </Safety Net>

# <CURL> Version that just downloads the latest version
# curl --fail -s -L https://api.github.com/repos/apache/pdfbox/git/refs/tags > /tmp/pdfbox.json
# VERSION=`jq -r '.[] | select(.ref|test("tags/2")).ref' < /tmp/pdfbox.json | tail -1 | cut -d/ -f 3`

MAJOR_VERSION=3

VERSION=`curl --fail -s -l https://projects.apache.org/json/projects/pdfbox.json | jq -r "[ .release[] | select(.revision|test(\"^$MAJOR_VERSION\")).revision ][0]"`

echo Installing version $VERSION
URL=https://dlcdn.apache.org/pdfbox/$VERSION/pdfbox-app-$VERSION.jar
echo Downloading from $URL

curl --fail -q -L $URL > pdfbox.jar > /usr/share/java/pdfbox.jar
# </CURL>

# <MAVEN> Version that builds our own from Maven
# cd /usr/src/pdfbox
# mvn clean
# mvn install
# if [[ -f target/pdfbox-app-2.0.25.jar ]];
# then
#     cp target/pdfbox-app-2.0.25.jar /usr/share/java/pdfbox.jar
# else
#     echo "Compilation failed."
#     exit 5
# fi
# </MAVEN>
