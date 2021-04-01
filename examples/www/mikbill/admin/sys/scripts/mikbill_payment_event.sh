#!/bin/bash

# Файл из репозитория MikBill-EventSystem-PHP-Kit
# https://github.com/itpanda-llc/mikbill-eventsystem-php-kit

cd /var/mikbill/__ext/vendor/itpanda-llc/mikbill-eventsystem-kit/scripts/ || exit

#/usr/bin/php ./APIPaymentSMSPilotNotice.php "$2" > /dev/null 2>&1
#/usr/bin/php ./APIReturnSMSPilotNotice.php "$2" > /dev/null 2>&1
#/usr/bin/php ./PaymentSMSPilotNotice.php "$2" > /dev/null 2>&1
#/usr/bin/php ./ReturnSMSPilotNotice.php "$2" > /dev/null 2>&1
#/usr/bin/php ./LimitSMSPilotNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./APIPaymentSMSCenterNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./APIReturnSMSCenterNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentSMSCenterNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./ReturnSMSCenterNotice.php "$2" > /dev/null 2>&1
/usr/bin/php ./LimitSMSCenterNotice.php "$2" > /dev/null 2>&1
#/usr/bin/php ./PaymentAmountBonusLog.php "$2" > /dev/null 2>&1
#/usr/bin/php ./PaymentLoyaltyBonusLog.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentKomtetReceiptSend.php "$2" > /dev/null 2>&1
/usr/bin/php ./ReturnKomtetReceiptSend.php "$2" > /dev/null 2>&1
/usr/bin/php ./PaymentRouterOSSessionRemove.php "$2" > /dev/null 2>&1
/usr/bin/php ./ReturnRouterOSSessionRemove.php "$2" > /dev/null 2>&1
/usr/bin/php ./LimitRouterOSSessionRemove.php "$2" > /dev/null 2>&1
