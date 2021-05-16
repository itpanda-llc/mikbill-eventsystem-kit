#!/bin/bash

# Файл из репозитория MikBill-EventSystem-Kit
# https://github.com/itpanda-llc/mikbill-eventsystem-kit

cd /var/mikbill/__ext/vendor/itpanda-llc/mikbill-eventsystem-kit/scripts/ || exit

#/usr/bin/php ./FreezeSMSPilotNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./FreezeSMSCenterNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./FreezeDiscountRemove.php "$2" > /dev/null 2>&1
/usr/bin/php ./RouterOSSessionRemove.php "$2" > /dev/null 2>&1
