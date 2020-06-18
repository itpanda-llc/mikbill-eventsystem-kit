#!/bin/bash

cd ../scripts/ || exit
#/usr/bin/php ./PaymentSMSNotice.php "$1" > /dev/null 2>&1
#/usr/bin/php ./ReturnSMSNotice.php "$1" > /dev/null 2>&1
#/usr/bin/php ./LimitSMSNotice.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentSocNotice.php "$1" > /dev/null 2>&1
/usr/bin/php ./ReturnSocNotice.php "$1" > /dev/null 2>&1
/usr/bin/php ./LimitSocNotice.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentKomtetReceiptSend.php "$1" > /dev/null 2>&1
/usr/bin/php ./ReturnKomtetReceiptSend.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentBonusLog.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentLoyaltyBonusLog.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentRouterOSSessionRemove.php "$1" > /dev/null 2>&1
/usr/bin/php ./ReturnRouterOSSessionRemove.php "$1" > /dev/null 2>&1
/usr/bin/php ./LimitRouterOSSessionRemove.php "$1" > /dev/null 2>&1