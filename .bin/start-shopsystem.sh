#!/bin/bash
set -e

export MAGENTO_CONTAINER_NAME=web
export MYSQL_DATABASE=magento
export MYSQL_USER=magento
export MYSQL_PASSWORD=magento

docker-compose build --build-arg MAGENTO_VERSION=${MAGENTO2_VERSION} web
docker-compose up -d
sleep 30
while ! $(curl --output /dev/null --silent --head --fail "${NGROK_URL}"); do
    echo "Waiting for docker container to initialize"
    sleep 5
done

# install magento shop
docker exec -it ${MAGENTO_CONTAINER_NAME} install-magento.sh
docker exec -it ${MAGENTO_CONTAINER_NAME} install-sampledata.sh

# install wirecard magento2 plugin
docker exec -it ${MAGENTO_CONTAINER_NAME} composer require wirecard/magento2-ee:dev-master
docker exec -it ${MAGENTO_CONTAINER_NAME} php bin/magento setup:upgrade
docker exec -it ${MAGENTO_CONTAINER_NAME} php bin/magento setup:di:compile
#this gives the shop time to init
curl $NGROK_URL --head
sleep 30
curl $NGROK_URL --head

echo "\nModify File Permissions To Load CSS!\n"
docker exec -it ${MAGENTO_CONTAINER_NAME} bash -c "chmod -R 777 ./"

# change gateway if so configured
docker exec --env MYSQL_DATABASE=${MYSQL_DATABASE} \
            --env MYSQL_USER=${MYSQL_USER} \
            --env MYSQL_PASSWORD=${MYSQL_PASSWORD} \
            --env GATEWAY=${GATEWAY} \
            ${MAGENTO_CONTAINER_NAME} bash -c "cd /magento2-plugin/tests/_data/ && php configure_payment_method_db.php creditcard"

# clean cache to activate payment method
docker exec -it ${MAGENTO_CONTAINER_NAME} php bin/magento cache:clean
docker exec -it ${MAGENTO_CONTAINER_NAME} php bin/magento cache:flush
