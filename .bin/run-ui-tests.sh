#!/bin/bash
set -e

export VERSION=`jq .[0].release SHOPVERSIONS`

curl -s https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-linux-amd64.zip > ngrok.zip
unzip ngrok.zip
chmod +x $PWD/ngrok

curl -sO http://stedolan.github.io/jq/download/linux64/jq
chmod +x $PWD/jq

$PWD/ngrok authtoken $NGROK_TOKEN
TIMESTAMP=$(date +%s)
$PWD/ngrok http 9090 -subdomain="${TIMESTAMP}-magento2-${GATEWAY}-${MAGENTO2_RELEASE_VERSION}" > /dev/null &

NGROK_URL_S=$(curl -s localhost:4040/api/tunnels/command_line | jq --raw-output .public_url)

while [ ! ${NGROK_URL_S} ] || [ ${NGROK_URL_S} = 'null' ];  do
    echo "Waiting for ngrok to initialize"
    NGROK_URL_S=$(curl -s localhost:4040/api/tunnels/command_line | jq --raw-output .public_url)
    export NGROK_URL=$(sed 's/https/http/g' <<< "$NGROK_URL_S")
    echo $NGROK_URL
    sleep 1
done

bash .bin/start-shopsystem.sh
vendor/bin/codecept run acceptance --debug --html --xml
