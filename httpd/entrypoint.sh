#!/usr/bin/env bash

set -e

# переносим значения переменных из текущего окружения
env | while read -r LINE; do  # читаем результат команды 'env' построчно
    # делим строку на две части, используя в качестве разделителя "=" (см. IFS)
    IFS="=" read VAR VAL <<< ${LINE}
    # удаляем все предыдущие упоминания о переменной, игнорируя код возврата
    sed --in-place "/^${VAR}/d" /etc/security/pam_env.conf || true
    # добавляем определение новой переменной в конец файла
    echo "${VAR} DEFAULT=\"${VAL}\"" >> /etc/security/pam_env.conf
done

# Пытаемся удалить pid-файл, если он есть
rm -rf /run/httpd/httpd.pid || true

#Запуск демона крона и установка дефолтного кронтаба
if [[ $(pgrep crond | wc -l) = 0 ]]; then
  crond -s -n &
fi
crontab -u bitrix /root/crontab.cfg

#Перегенерация ssh-ключа и смена пароля, чтобы паролем можно было управлять через переменные окружения
cd /etc/ssh && ssh-keygen -A
cd /home/bitrix/www

if [[ -n $SSH_PASSWORD ]]; then
  echo "$SSH_PASSWORD" | passwd bitrix --stdin
fi

/usr/sbin/sshd -D &

#Выдаем нужные права на домашнюю папку
chown bitrix:bitrix -R /home/bitrix/www

if [ -d /home/bitrix/.ssh ]; then
  chown -R bitrix:bitrix /home/bitrix/.ssh
  chmod 700 /home/bitrix/.ssh
  if [[ $(ls /home/bitrix/.ssh | wc -l) -gt 0 ]]; then
    chmod -R 600 /home/bitrix/.ssh/*
  fi
fi

#Выдаем права на папку с логами
chown bitrix:bitrix -R /var/log/httpd

composer install

exec "$@"