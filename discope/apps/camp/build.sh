#!/bin/bash
rm -rf .angular
# Windows : npm link is necessary to link sb-shared-lib from the global node_modules folder
npm link sb-shared-lib
# Windows : ng build --configuration production --output-hashing none --base-href="//camp\\"
# Linux : ng build --configuration production --base-href="/camp/"
ng build --configuration production --output-hashing none --base-href="//camp\\"
touch manifest.json && rm -f web.app && cp manifest.json dist/symbiose/ && cd dist/symbiose && zip -r ../../web.app * && cd ../..
cat web.app | md5sum | awk '{print $1}' > version
cat version
