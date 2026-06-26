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

CDN_BASE="https://dlcdn.apache.org/pdfbox/${VERSION}"
CANONICAL_BASE="https://downloads.apache.org/pdfbox/${VERSION}"
JAR_FILE="pdfbox-app-${VERSION}.jar"
JAR_PATH="/usr/share/java/pdfbox.jar"

echo "Downloading JAR from CDN..."
curl --fail -L "${CDN_BASE}/${JAR_FILE}" -o "${JAR_PATH}"

echo "Verifying SHA-512 checksum..."
curl --fail -L "${CDN_BASE}/${JAR_FILE}.sha512" -o /tmp/pdfbox.jar.sha512
EXPECTED_HASH=$(cat /tmp/pdfbox.jar.sha512)
echo "${EXPECTED_HASH}  ${JAR_PATH}" | sha512sum -c - || {
    echo "ERROR: SHA512 checksum verification failed — downloaded jar may be corrupt or tampered with." >&2
    rm -f "${JAR_PATH}"
    exit 1
}
rm -f /tmp/pdfbox.jar.sha512

# PGP signature fetched from canonical Apache server (different trust root from CDN mirror above)
echo "Verifying PGP signature from canonical Apache server..."
curl --fail -L "${CANONICAL_BASE}/${JAR_FILE}.asc" -o /tmp/pdfbox.jar.asc
curl --fail -L "https://downloads.apache.org/pdfbox/KEYS" -o /tmp/pdfbox-KEYS

GNUPGHOME=$(mktemp -d)
export GNUPGHOME
gpg --import /tmp/pdfbox-KEYS 2>/dev/null
gpg --verify /tmp/pdfbox.jar.asc "${JAR_PATH}" || {
    echo "ERROR: PGP signature verification failed — jar may be tampered with." >&2
    rm -f "${JAR_PATH}"
    rm -rf "${GNUPGHOME}"
    exit 1
}
rm -rf "${GNUPGHOME}" /tmp/pdfbox.jar.asc /tmp/pdfbox-KEYS
echo "PGP verification passed."
