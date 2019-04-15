#!/bin/bash
set -e

export MAGENTO_CONTAINER_NAME=web

docker-compose build --build-arg GATEWAY=${GATEWAY} ${MAGENTO_CONTAINER_NAME}
docker-compose up > /dev/null &

while ! $(curl --output /dev/null --silent --head --fail "${NGROK_URL}"); do
    echo "Waiting for docker container to initialize"
    sleep 5
done

# install magento shop
docker exec -it ${MAGENTO_CONTAINER_NAME} install-magento
docker exec -it ${MAGENTO_CONTAINER_NAME} install-sampledata

#install wirecard magento2 plugin
docker exec -it ${MAGENTO_CONTAINER_NAME} composer require wirecard/magento2-ee
docker exec -it ${MAGENTO_CONTAINER_NAME} php bin/magento setup:upgrade
docker exec -it ${MAGENTO_CONTAINER_NAME} php bin/magento setup:di:compile

echo "Make folders writeable and find configuration file"
docker exec -it ${MAGENTO_CONTAINER_NAME} bash -c "chmod -R 777 ./"
sleep 10m
