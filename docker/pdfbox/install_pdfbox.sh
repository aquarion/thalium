#!/bin/bash

#### <Safety Net>
set -o errexit # Exit immediately if a pipeline returns non-zero.
trap 'echo "Aborting due to errexit on line $LINENO. Exit code: $?" >&2' ERR # Print a helpful message if that happens
set -o errtrace # Allow the above trap be inherited by all functions in the script.
set -o pipefail # Return code of a pipeline is the right-most failure. 0 if none.
#### </Safety Net>

MAJOR_VERSION=3

API_RESPONSE=$(curl --fail -s -L https://projects.apache.org/json/projects/pdfbox.json) || {
    echo "ERROR: Failed to contact Apache projects API." >&2
    exit 1
}

if [[ -z "$API_RESPONSE" ]]; then
    echo "ERROR: Apache projects API returned an empty response." >&2
    exit 1
fi

VERSION=$(echo "$API_RESPONSE" | jq -r "[ .release[] | select(.name == \"Apache PDFBox\" and (.revision|test(\"^$MAJOR_VERSION\"))).revision ][0]") || {
    echo "ERROR: Failed to parse Apache projects API response." >&2
    echo "$API_RESPONSE" | head -5 >&2
    exit 1
}

if [[ -z "$VERSION" || "$VERSION" == "null" ]]; then
    echo "ERROR: Could not determine PDFBox $MAJOR_VERSION.x version from Apache projects API." >&2
    exit 1
fi

echo "Installing PDFBox version: ${VERSION}"
URL="https://dlcdn.apache.org/pdfbox/${VERSION}/pdfbox-app-${VERSION}.jar"
echo "Downloading from ${URL}"

curl --fail -L "${URL}" -o /usr/share/java/pdfbox.jar
curl --fail -L "${URL}.sha512" -o /tmp/pdfbox.jar.sha512

EXPECTED_HASH=$(cat /tmp/pdfbox.jar.sha512)
echo "${EXPECTED_HASH}  /usr/share/java/pdfbox.jar" | sha512sum -c - || {
    echo "ERROR: SHA512 checksum verification failed — downloaded jar may be corrupt or tampered with." >&2
    rm -f /usr/share/java/pdfbox.jar
    exit 1
}
rm -f /tmp/pdfbox.jar.sha512
