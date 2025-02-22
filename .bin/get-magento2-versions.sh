#!/bin/bash

curl -H "Authorization: token ${GITHUB_TOKEN}" https://api.github.com/repos/magento/magento2/releases | jq -r '.[] | .tag_name' | head -n3 > ${MAGENTO2_RELEASES_FILE}
git config --global user.name "Travis CI"
git config --global user.email "wirecard@travis-ci.org"

git add  ${MAGENTO2_RELEASES_FILE}
git commit -m "[skip ci] Update latest shop releases"
git push --quiet https://${GITHUB_TOKEN}@github.com/${TRAVIS_REPO_SLUG} HEAD:master
