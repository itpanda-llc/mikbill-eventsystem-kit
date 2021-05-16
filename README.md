# MikBill-EventSystem-Kit

Набор скриптов для [системы событий](https://wiki.mikbill.pro/billing/configuration/events) биллинговой системы ["MikBill"](https://mikbill.pro)

[![Packagist Downloads](https://img.shields.io/packagist/dt/itpanda-llc/mikbill-eventsystem-kit)](https://packagist.org/packages/itpanda-llc/mikbill-eventsystem-kit/stats)
![Packagist License](https://img.shields.io/packagist/l/itpanda-llc/mikbill-eventsystem-kit)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/itpanda-llc/mikbill-eventsystem-kit)

## Ссылки

* [Разработка](https://github.com/itpanda-llc)
* [О проекте (MikBill)](https://mikbill.pro)
* [Документация (MikBill)](https://wiki.mikbill.pro)

## Возможности

* Уведомление абонентов при платежных событиях, смене тарифного плана и реального IP-адреса, заморозке, блокировке и удалении аккаунта
* Зачисление денежных бонусов (лояльности и за пополнение счета)
* Формирование и отправка фискальных документов в облачный сервис ["Комтет Касса"](https://kassa.komtet.ru)
* Удаление параметров скидок при смене тарифного плана, заморозке, блокировке и удалении аккаунта
* Сброс PPP-сессий в маршрутизаторах семейства ["RouterOS"](https://mikrotik.com/software) при платежных событиях, смене тарифного плана и реального IP-адреса, заморозке, блокировке и удалении аккаунта

## Требования

* PHP >= 7.2
* JSON
* libxml
* PDO
* SimpleXML
* [EvilFreelancer/routeros-api-php](https://github.com/EvilFreelancer/routeros-api-php)
* [itpanda-llc/smscenter-messenger-sdk](https://github.com/itpanda-llc/smscenter-messenger-sdk)
* [itpanda-llc/smspilot-messenger-sdk](https://github.com/itpanda-llc/smspilot-messenger-sdk)
* [Komtet/komtet-kassa-php-sdk](https://github.com/Komtet/komtet-kassa-php-sdk)

## Установка

```shell script
composer require itpanda-llc/mikbill-eventsystem-kit
```

## Конфигурация

Указание

* Путей к [конфигурационному файлу](https://wiki.mikbill.pro/billing/config_file), интерфейсам и значений констант в [файлах-скриптах](scripts)
* В файлах биллинговой системы путей и параметров к [файлам-скриптам](scripts) (см. далее)

Для системы

* [/var/www/mikbill/admin/sys/scripts/mb_event_realip_change.sh](examples/www/mikbill/admin/sys/scripts/mb_event_realip_change.sh)
* [/var/www/mikbill/admin/sys/scripts/mikbill_onoff_user_event.sh](examples/www/mikbill/admin/sys/scripts/mikbill_onoff_user_event.sh)
* [/var/www/mikbill/admin/sys/scripts/mikbill_payment_event.sh](examples/www/mikbill/admin/sys/scripts/mikbill_payment_event.sh)
* [/var/www/mikbill/admin/sys/scripts/mikbill_tarif_change_event.sh](examples/www/mikbill/admin/sys/scripts/mikbill_tarif_change_event.sh)

Для кабинета

* [/var/www/mikbill/stat/sys/scripts/mb_event_realip_change.sh](examples/www/mikbill/stat/sys/scripts/mb_event_realip_change.sh)
* [/var/www/mikbill/stat/sys/scripts/mikbill_onoff_user_event.sh](examples/www/mikbill/stat/sys/scripts/mikbill_onoff_user_event.sh)
* [/var/www/mikbill/stat/sys/scripts/mikbill_payment_event.sh](examples/www/mikbill/stat/sys/scripts/mikbill_payment_event.sh)
* [/var/www/mikbill/stat/sys/scripts/mikbill_tarif_change_event.sh](examples/www/mikbill/stat/sys/scripts/mikbill_tarif_change_event.sh)
