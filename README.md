# Core documentation
Ядро предназначно для следующего:
- Принимать данные со всех gates и сохранять их в memcached
- Посылать команды гейтам
- Принимать конфиг с агента
- Посылать все логи агенту
- Просчитывать алгоритм

## Core Service Structure
- kernel - папка, главная папка, связанная с принятием всех данных от gate и agent, а также содержит сам алгоритм
- config - папка, содержащая все настройки
- src - папка, содержащая все вспомогательные и рабочие класса
- test - папка, содержащая тестовые файлы для работы и проверки кода
- index.php - главный файл, подключеймый во всех файлах проекта

## Установка необходимого окружения и проекта
0. Необходимо, чтобы aeron уже был установлен для php 8.0
(https://github.com/RoboTradeCode/aeron-php)


1. Сделать предварительные команды
```shell
sudo apt update && sudo apt upgrade -y
sudo apt-get install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt install php8.0
```

2. Проверить, что php установился (версия должна быть PHP 8.0)
```shell
php -v
```

3. Далее установить библиотеки.
```shell
sudo apt install php8.0-common && sudo apt install php8.0-cli && sudo apt install php8.0-fpm && sudo apt install php8.0-mysql && sudo apt install php8.0-memcache && sudo apt install php8.0-memcached -y && sudo apt install php8.0-bcmath && sudo apt install php8.0-curl
```

4. Необходимо установить memcached
```shell
sudo apt install memcached
sudo apt install -y php-memcached
sudo apt install -y php8.0-memcached
```

5. Проверить работает ли memcached 
```shell
sudo service memcached status
```
Если не работает, то можно обратиться к статьям, чтобы решить проблему
(https://habr.com/ru/post/108274/)
(https://sheensay.ru/memcached-install-config#kak-ustanovit-server-memcached)
или побробовать следующие команды
```shell
sudo apt update
sudo apt install memcached
sudo apt install libmemcached-tools -y
sudo systemctl start memcached
sudo apt-get install php8.0-memcache
```

6. Установка composer (https://getcomposer.org/download/)

7. Проверить версию (она обязательно должна быть не меньше 2.0.0)
Если версия ниже 2.0.0, обратиться к статье (https://coderteam.ru/blog/obnovlyaemsya-do-composer-2-na-ubuntu/)
Хотя, есть вероятность, что и в первой версии тоже будет работать.
```shell
composer
```

8. Клонирование репозитория (Если ссылка не подходит, скопировать ее, на гитхаб в репозитории -> code -> https -> копирование значок)
```shell
git clone --recurse-submodules https://github.com/RoboTradeCode/core_3t_php.git
```

9. перейти в папку core_3t_php
```shell
cd core_3t_php/
```

## Установка зависимостей
```shell
composer install
```

## Cross 3t алгоритма
1. Первый шаг - запуск получения данных от гейта.
```shell
php kernel/receive_data.php
```

2. Запустить сам алгоритм cross_3t_php.
```shell
php kernel/cross_3t.php
```

Первоначальные данные:
1) Все ордербуки со всех бирж
2) Все балансы со всех бирж
3) Настройки (min_deal_amount, курсы как amount, routes и т. д.)

Логика алгоритма:
1) Фильтрация баланса по min_deal_amount, оставить только те ассеты, которые могут быть потенциально отторгованы
2) Проходиться по всем routes (треугольникам), в которых один из шагов (route) имеет актив, который есть на главной бирже. Если биржи или ассета из баланса нет в routes, то пропустить этот треугольник
3) Находим размер сделки deal_amount в зависимости от балансов и max_deal_amount
4) Выбираем лучшие ордербуки идя в глубь стакана, и в зависимости от deal_amount (запоминаем, какая пара относится к какой бирже)
5) Записать результат сделки в массив для каждого треугольника
6) Выбираем лучший результат и если он положительный, совершаем сделку на станции если есть эта биржа в результате

## Балансировочный алгоритм
1. Первый шаг - запуск получения данных от гейта.
```shell
php kernel/receive_data.php
```

2. Запустить сам алгоритм balancer_by_market_order.
```shell
php kernel/balancer_by_market_order.php
```

Логика алгоритма:
1) Балансирует всю сумму к USDT
2) Делит всю сумму поровну на все ассеты
3) Отправляет запросы на постановку маркет ордеров для каждого ассета
