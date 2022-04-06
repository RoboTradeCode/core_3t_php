# Core documentation
Ядро предназначно для следующего:
- Принимать данные со всех gates и сохранять их в memcached
- Посылать команды гейтам
- Принимать конфиг с агента
- Посылать все логи агенту
- Просчитывать алгоритм

## Core Service Structure
- algo - папка, содержащая все написанные алгоритмы
- kernel - папка, связанная с принятием всех данных от gate и agent
- src - папка, содержащая все вспомогательные и рабочие класса
- test - папка, содержащая тестовые файлы для работы и проверки кода
- index.php - главный файл, подключеймый во всех файлах проекта

## Установка зависимостей
```shell
composer install
```