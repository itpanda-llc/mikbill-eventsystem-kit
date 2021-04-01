#!/bin/bash

# Файл из репозитория MikBill-EventSystem-PHP-Kit
# https://github.com/itpanda-llc/mikbill-eventsystem-php-kit

cd /var/mikbill/__ext/vendor/itpanda-llc/mikbill-eventsystem-kit/scripts/ || exit

#/usr/bin/php ./TarifChangeClientSMSPilotNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./TarifChangeClientSMSCenterNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./DiscountRemove.php "$2" > /dev/null 2>&1
/usr/bin/php ./RouterOSSessionRemove.php "$2" > /dev/null 2>&1
