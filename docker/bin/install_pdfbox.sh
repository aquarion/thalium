
#/bin/bash

#### <Safety Net>
set -o errexit # Exit immediately if a pipeline returns non-zero.
trap 'echo "Aborting due to errexit on line $LINENO. Exit code: $?" >&2' ERR # Print a helpful message if that happens
set -o errtrace # Allow the above trap be inherited by all functions in the script.
set -o pipefail # Return code of a pipeline is the right-most failure. 0 if none.
#### </Safety Net>

curl --fail -q -L https://api.github.com/repos/apache/pdfbox/git/refs/tags > /tmp/pdfbox.json
VERSION=`jq -r '.[] | select(.ref|test("tags/2")).ref' < /tmp/pdfbox.json | tail -1 | cut -d/ -f 3`
echo Installing version $VERSION
curl --fail -q -L https://downloads.apache.org/pdfbox/$VERSION/pdfbox-app-$VERSION.jar > /usr/share/java/pdfbox.jar