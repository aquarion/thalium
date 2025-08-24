#!/bin/bash

set -e

if [[ -z $1 ]]; then
  echo "Usage: $0 <version>"
  exit 1
fi

VERSION=$1

docker build . -f docker/Dockerfile/thalium.Dockerfile -t isticco/thalium_base:$VERSION
docker push isticco/thalium_base:$VERSION
