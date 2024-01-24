# Sandbox docker manager

Сервис для управления песочницами [sandbox_docker_env](https://github.com/serdcebolit/sandbox_docker_env)

Расчитан на запуск в общей инфраструктуре (доступ через главный traefik).

Для запуска нужно скопировать файл .env.example в .env и заполнить переменные. Затем выполнить команду:

```
docker-compose up -d
```

## Запуск для разработки

Задать переменную окружения SANDBOXES_ROOT_PATH равной ./ext_www Именно в этой папке будут создаваться все песочницы.
В переменной SITE_HOST задать значение manager.local.gd (сервис local.gd перенаправляет все поддомены на 127.0.0.1, что позволяет не прописывать их в файл hosts).

```
docker-compose -f docker-compose.yml -f docker-compose.local.yml up -d
```

После этого сайт будет доступен по адресу http://manager.local.gd:8081

В папку www/project_stub/ склонировать репозиторий https://gitlab.intervolga.ru/common/ivdev_docker_env
