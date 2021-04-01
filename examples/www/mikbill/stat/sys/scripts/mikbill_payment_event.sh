#!/bin/bash

# Файл из репозитория MikBill-EventSystem-PHP-Kit
# https://github.com/itpanda-llc/mikbill-eventsystem-php-kit

cd /var/mikbill/__ext/vendor/itpanda-llc/mikbill-eventsystem-kit/scripts/ || exit

#/usr/bin/php ./PaymentSMSPilotNotice.php "$2" > /dev/null 2>&1
#/usr/bin/php ./CreditSMSPilotNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentSMSCenterNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./CreditSMSCenterNotice.php "$2" > /dev/null 2>&1
#/usr/bin/php ./PaymentAmountBonusLog.php "$2" > /dev/null 2>&1
#/usr/bin/php ./PaymentLoyaltyBonusLog.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentKomtetReceiptSend.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentRouterOSSessionRemove.php "$2" > /dev/null 2>&1
/usr/bin/php ./CreditRouterOSSessionRemove.php "$2" > /dev/null 2>&1
