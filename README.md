# MikBill-EventSystem-PHP-Kit

Набор PHP-скриптов в дополнение функционалу биллинговой системы [АСР "MikBill"](https://mikbill.pro), использующий API "Системы событий" продукта

[![GitHub license](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

## Ссылки

* [Разработка](https://github.com/itpanda-llc)
* [О проекте (АСР "MikBill")](https://mikbill.pro)
* [Документация (АСР "MikBill")](https://wiki.mikbill.pro)
* [Сообщество (АСР "MikBill")](https://mikbill.userecho.com)

## Возможности

* СМС-уведомление и уведомление в социальных сетях ("ВКонтакте", "Одноклассники") абонентов при "платежных событиях" (кредит, лимит, платеж, возврат), а также смене тарифного плана и дальнейшее занесение информации в БД
* Формирование денежных бонусов (лояльности и за пополнение счета) с занесением в БД (подразумевается дальнейшее зачисление на счета, используя API "Системы платежей" биллинговой системы)
* Формирование фискального документа, отправка в облачный сервис [Комтет Касса](https://kassa.komtet.ru) и дальнейшая запись информации в БД
* Удаление параметров скидок при смене тарифного плана (Глобальная и расширенная: тариф, подписки, аренда)
* "Сброс" PPP-сессий при "платежных событиях" (кредит, лимит, платеж, возврат), а также смене тарифного плана в маршрутизаторах (NAS) семейства [RouterOS](https://mikrotik.com)

## Требования

* CentOS >= 7
* PHP >= 7.2
* PDO
* SimpleXML
* ssh2
* JSON
* [smspilot-messenger-php-sdk](https://github.com/itpanda-llc/smspilot-messenger-php-sdk) (для СМС-уведомлений)
* [smsc-sender-php-sdk](https://github.com/itpanda-llc/smsc-sender-php-sdk) (для уведомлений в социальных сетях)
* [komtet-kassa-php-sdk](https://github.com/Komtet/komtet-kassa-php-sdk) (для отправки докуменов в сервис [Комтет Касса](https://kassa.komtet.ru))


* !! Для продолжения функционала репозитория и расширения возможностей, при пользовании системой [АСР "MikBill"](https://mikbill.pro), дополнительно, рекомендовано применять набор [mikbill-daemonsystem-php-kit](https://github.com/itpanda-llc/mikbill-daemonsystem-php-kit), осуществляющий другие (остальные) полезные действия, вне "Системы событий" биллинга.

## Рекомендуемая установка и подготовка

Создание и переход в директорию, например "mkdir /var/mikbill/__ext/ && cd /var/mikbill/__ext/".

Клонирование необходимых репозиториев:

* git clone https://github.com/itpanda-llc/mikbill-eventsystem-php-kit
* git clone https://github.com/itpanda-llc/mikbill-daemonsystem-php-kit (...пригодится)
* git clone https://github.com/itpanda-llc/smspilot-messenger-php-sdk (для СМС-уведомлений)
* git clone https://github.com/itpanda-llc/smsc-sender-php-sdk (для уведомлений в социальных сетях)
* git clone https://github.com/Komtet/komtet-kassa-php-sdk (для отправки докуменов в сервис [Комтет Касса](https://kassa.komtet.ru))

Установка прав доступа "chmod -R 755 ./mikbill-eventsystem-php-kit/.sh/".

Конфигурация скриптов (корректирование путей, констант и значений) по пути "/var/mikbill/__ext/mikbill-eventsystem-php-kit/scripts/"

Редактирование файлов биллинговой системы (предварительно, добавление строки "cd /var/mikbill/__ext/mikbill-eventsystem-php-kit/.sh/" во все файлы):

* /var/www/mikbill/admin/sys/scripts/mikbill_payment_event.sh - добавление строки "./PaymentSystem.sh "$2""
* /var/www/mikbill/admin/sys/scripts/mikbill_tarif_change_event.sh - добавление строки "./TarifChangeSystem.sh "$2""
* /var/www/mikbill/stat/sys/scripts/mikbill_payment_event.sh - добавление строки "./PaymentClient.sh "$2""
* /var/www/mikbill/stat/sys/scripts/mikbill_tarif_change_event.sh - добавление строки "./TarifChangeClient.sh "$2""

Пример файла "/var/www/mikbill/admin/sys/scripts/mikbill_payment_event.sh"

```shell script
#!/bin/bash

cd /var/mikbill/__ext/mikbill-eventsystem-php-kit/.sh/ || exit
./PaymentSystem.sh "$2"
```

или

```shell script
#!/bin/bash

cd /var/mikbill/__ext/mikbill-eventsystem-php-kit/scripts/ || exit
#/usr/bin/php ./PaymentSMSNotice.php "$2" > /dev/null 2>&1
#/usr/bin/php ./ReturnSMSNotice.php "$2" > /dev/null 2>&1
#/usr/bin/php ./LimitSMSNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentSocNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./ReturnSocNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./LimitSocNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentKomtetReceiptSend.php "$2" > /dev/null 2>&1
/usr/bin/php ./ReturnKomtetReceiptSend.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentBonusLog.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentLoyaltyBonusLog.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentRouterOSSessionRemove.php "$2" > /dev/null 2>&1
/usr/bin/php ./ReturnRouterOSSessionRemove.php "$2" > /dev/null 2>&1
/usr/bin/php ./LimitRouterOSSessionRemove.php "$2" > /dev/null 2>&1
```

## Описание скриптов и логики действия

Файлы "(.*)SMSNotice.php" осуществляют отправку СМС-сообщения и его дальнейшую запись в БД

Файлы "(.*)SocNotice.php" осуществляют отправку сообщения в социальные сети и его дальнейшую запись в БД

Файлы "(.*)ReceiptSend.php" осуществляют отправку данных в сервис облачной кассы и их дальнейшую запись в БД

Файлы "(.*)DiscountRemove.php" осуществляют удаление параметров скидок

Файлы "(.*)SessionRemove.php" осуществляют "сброс" PPP-сессии на серверах доступа (NAS)

Файлы "(.*)BonusLog.php" осуществляют формирование бонуса и его дальнейшую запись в БД

##### ..Каждый файл самостоятелен и независим от соседних. Для понимания логики действия и условий срабатывания программ в подробностях, необходимо изучение SQL-запросов в скриптах..
