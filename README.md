# Core documentation
Ядро предназначно для следующего:
- Принимать данные со всех gates и сохранять их в memcached
- Посылать команды гейтам
- Принимать конфиг с агента
- Посылать все логи агенту
- Просчитывать алгоритм

## Core Service Structure
- kernel - папка, главная папка, связанная с принятием всех данных от gate и agent, а также содержит сам алгоритм
- src - папка, содержащая все вспомогательные и рабочие класса
- test - папка, содержащая тестовые файлы для работы и проверки кода
- index.php - главный файл, подключеймый во всех файлах проекта

## Установка зависимостей
```shell
composer install
```

## Инструкция установки Core
0. Перед запуском ядра, необходимо, чтобы был запущен агент.

1. Первый шаг - запуск получения данных от агента и гейта.
```shell
php kernel/receive_data.php
```

2. Запустить сам алгоритм cross_3t_php.
```shell
php kernel/cross_3t.php
```
