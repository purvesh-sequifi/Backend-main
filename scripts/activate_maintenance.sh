#!/usr/bin/bash

if [ "$MAINTENANCE_MODE" = "true" ]; then
    sudo mkdir -p $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER &&
    cd $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER &&
    sudo git clone --branch=$BITBUCKET_BRANCH https://x-token-auth:$REPOSITORY_OAUTH_ACCESS_TOKEN@bitbucket.org/$BITBUCKET_REPO_FULL_NAME.git $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER &&
    sudo chmod -R 777 $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER &&
    sudo chown -R www-data:www-data $DEPLOY_PATH &&
    cd $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER && 
    sudo php artisan down &&
    sudo unlink $DEPLOY_PATH/current &&
    sudo ln -s $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER/maintaince $DEPLOY_PATH/current
else
    cd /var/www/backend/current &&
    sudo cp -r public/ ../ &&
    cd &&
    sudo mkdir -p $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER &&
    cd $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER &&
    sudo git clone --branch=$BITBUCKET_BRANCH https://x-token-auth:$REPOSITORY_OAUTH_ACCESS_TOKEN@bitbucket.org/$BITBUCKET_REPO_FULL_NAME.git $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER &&
    sudo chmod -R 777 $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER &&
    sudo php artisan up
    sudo aws ssm get-parameter --name /backend/uat --with-decryption --output text --query Parameter.Value > $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER/.env &&
    sudo chmod -R 777 $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER/storage &&
    sudo chown -R www-data:www-data $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER/storage &&
    sudo chown -R www-data:www-data $DEPLOY_PATH &&
    sudo unlink $DEPLOY_PATH/current &&
    sudo ln -s $DEPLOY_PATH/$BITBUCKET_BUILD_NUMBER $DEPLOY_PATH/current &&
    sudo mkdir -p /var/www/backend/current/storage/app/processed_pdf &&
    sudo mkdir -p /var/www/backend/current/storage/app/processed_pdf/e_signed_pdf &&
    sudo mkdir -p /var/www/backend/current/storage/app/processed_pdf/form_data_merged_pdf &&
    sudo mkdir -p /var/www/backend/current/storage/app/signed_pdfs &&
    sudo mkdir -p /var/www/backend/current/storage/app/unsigned_pdfs &&
    sudo chmod -R 777 /var/www/backend/current/storage/app/ &&
    sudo chown -R www-data:www-data /var/www/backend/current/storage/app/ &&
    cd /var/www/backend/current &&
    sudo rm -rf public &&
    sudo cp -r ../public/ . &&
    sudo chown -R www-data:www-data public &&
    sudo rm -rf ../public &&
    sudo composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction &&
    sudo php artisan migrate --force &&
    sudo php artisan key:generate &&
    sudo touch storage/logs/laravel.log &&
    sudo chmod 777 storage/logs/laravel.log &&
    sudo php artisan config:clear
fi
