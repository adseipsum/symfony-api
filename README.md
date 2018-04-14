domain-checker-web
=================


Процесс установки:

su dev -l

cd /home/dev/www/sapi/
git pull

composer install
php bin/console cache:clear --env prod --no-warmup
php bin/console cache:clear --env dev --no-warmup

------------------------------------------------------------------------------------------------------------------------

обновить зависимости компоузера

composer update

проинсталить зависимости компоузера:

composer install

почистить кэш

php bin/console cache:clear --env prod --no-warmup
php bin/console cache:clear --env dev --no-warmup

------------------------------------------------------------------------------------------------------------------------


Запуск локального сервера:

php bin/console server:run


Проверка настройки окружения php

php bin/symfony_requirements

------------------------------------------------------------------------------------------------------------------------
